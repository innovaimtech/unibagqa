<?php
$_sesmodulename         = "stats_prodseri";
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
   $_SESSION[$_sesmodulename]["sql_month1"]          = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]          = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]           = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]           = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]         = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]            = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_dateto"]          = trim($_REQUEST["sql_dateto"]);
   $_SESSION[$_sesmodulename]["sql_datefrom"]        = trim($_REQUEST["sql_datefrom"]);
   $_SESSION[$_sesmodulename]["sql_xstate"]          = (int)$_REQUEST["sql_xstate"];
   $_SESSION[$_sesmodulename]["sql_plantaid"]        = (int)$_REQUEST["sql_plantaid"];
   $_SESSION[$_sesmodulename]["sql_equipotypeid"]    = (int)$_REQUEST["sql_equipotypeid"];
   $_SESSION[$_sesmodulename]["sql_equipoid"]        = (int)$_REQUEST["sql_equipoid"];
   $_SESSION[$_sesmodulename]["sql_customer"]        = (int)$_REQUEST["sql_customer"];    
   $_SESSION[$_sesmodulename]["sql_number"]          = trim(addslashes($_REQUEST["sql_number"]));
   $_SESSION[$_sesmodulename]["sql_fab_design_name"] = trim(addslashes($_REQUEST["sql_fab_design_name"]));
   $_SESSION[$_sesmodulename]["page"]                = 0;
   $_SESSION[$_sesmodulename]["search_active"]       = 1;
}


//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 1;


//----------------------------------------------------------------------------------
/*
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m',time());
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y',time());
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m',time());
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y',time());
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_datefrom"] = date('d.m.Y', time());
   $_SESSION[$_sesmodulename]["sql_dateto"]   = date('d.m.Y', time() + (86400 * 7));
}
*/
//----------------------------------------------------------------------------------
/*
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
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_datefrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_dateto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
*/
//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
//
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y')-10;
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_datefrom"] = date('d.m.Y', time() - (86400 * 7));
   $_SESSION[$_sesmodulename]["sql_dateto"]   = date('d.m.Y');
}

/* -------------------------------------------------------------------------------------*/
$sql = "select * from parametros where tabla ='PROCESOBOBIN' and codigo = '1'";
$procesobobina = $CON->select($sql);
$procesobobina = $procesobobina[0];

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
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_datefrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_dateto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}


//----------------------------------------------------------------------------------
$plantas = getPlantas($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_plantaid"])
   $_SESSION[$_sesmodulename]["sql_plantaid"] = $plantas[0]["id"];
   $sql = " select req_production_initdate as 'fecha_hora_ingreso_cc' 
                 , req_number as 'numero_cc' 
                 , item_amount as 'cantidad_bolsas' 
                 , prd_number as 'numeroot' 
                 , cust_name as 'cliente' 
                 , t11.id  as 'Tipo_Bolsa' 
                 , concat(cast(fab_med_width as UNSIGNED) ,'x',cast(fab_med_height as UNSIGNED),'x',cast(fab_med_fuelle as UNSIGNED) ) as 'formato' 
                 , item_number_prod as 'codigo_producto' 
                 , item_title as 'descripcion_producto' 
                 -- , cat_prefix as 'Tipo_Bolsa'  
                 , 0 as 'ancho_bolsa' 
                 , 0 as 'alto_frente_bolsa' 
                 , 0 as 'alto_dorso_bolsa' 
                 , 0 as 'medida_doblez_superior' 
                 , 0 as 'medida_fuelle_bolsa' 
                 , 0 as 'largo_manilla'
                 , 'sin informacion' as 'color_tela' 
                 , fab_mat_fabric_color as 'codigo_color_tela' 
                 , item_reg_gsm as 'gramaje' 
                 , 0 as 'ancho_bobina' 
                 , t10.fab_type as 'Codigo_Tela' 
                 , 's/i' as 'cantidad_bobina' 
                 , req_pie_imprenta as 'pie_imprenta' 
                 , (case when item_sellprice_barcodenumber = '' then 'NO' else 'SI' end) as 'codigo_barra' 
                 , (case when item_sellprice_barcodenumber = '' then '0' else item_sellprice_barcodenumber end) as 'numero_codigo_barra' 
                 , (case when fab_mat_dispositivo = '' then 'NO' else 'SI' end) as 'tien_dispositivo' 
                 , 'sin informacion' as 'dispositivo' 
                 , equipo_name as 'nombre_maquina'
                 , t7.equipo_type_id as 'numero_maquina' 
                 , space(1000) as 'nombre_supervisor' 
                 , space(100) as 'rut_supervisor' 
                 , 0 as 'fecha_hora_i_alistamiento' 
                 , 0 as 'fecha_hora_t_alistamiento' 
                 , 0 as 'total_horas_alistamiento' 
                 , 0 as 'fecha_hora_i_produccion' 
                 , 0 as 'fecha_hora_t_produccion'
                 , 0 as 'total_horas_produccion' 
                 , '' as 'turno' 
                 , '' as 'horas_turno' 
                 , '' as 'nombre_operador' 
                 , '' as 'rutoperador' 
                 , '' as 'nombre_ayudante' 
                 , '' as 'rut_ayudante' 
                 , req_operador_mermaperc as 'porcentaje_merma' 
                 , fab_printtype as 'tipoimpresion' 
                 , fab_print_colors_front_1 
                 , fab_print_colors_front_2 
                 , fab_print_colors_front_3 
                 , fab_print_colors_front_4 
                 , fab_print_colors_front_5 
                 , fab_print_colors_front_6 
                 , fab_print_colors_front_7 
                 , fab_print_colors_front_8 
                 , fab_print_colors_front_9 
                 , (case when ifnull(fab_print_colors_front_10,'') = '' then 'Sin Información' else fab_print_colors_front_10 end) as fab_print_colors_front_10
                 , fab_print_colors_back_1 
                 , fab_print_colors_back_2 
                 , fab_print_colors_back_3 
                 , fab_print_colors_back_4 
                 , fab_print_colors_back_5 
                 , fab_print_colors_back_6 
                 , fab_print_colors_back_7 
                 , fab_print_colors_back_8 
                 , fab_print_colors_back_9 
                 , fab_print_colors_back_10 
                 , fab_print_colordesc_1 
                 , fab_print_colordesc_2 
                 , fab_print_colordesc_3 
                 , fab_print_colordesc_4 
                 , fab_print_colordesc_5 
                 , fab_print_colordesc_6 
                 , fab_print_colordesc_7 
                 , fab_print_colordesc_8 
                 , fab_print_colordesc_9 
                 , fab_print_colordesc_10
                 , t1.id 
                 , t1.wok_ag_id
                 , t9.req_solic_devprints_poltype 
                 , t11.item_prodcalc_fuelle_act
                 , t9.id as IdCC
                 , t3.win_equipoid
                 , t3.id as idworker
                 , t7.equipo_prod_isprinter_flexo
                 , t7.equipo_prod_isprinter_seri
                 , t1.wok_crtdat
                 , t1.wok_enddat
                 , equipo_prod_divisor_perc
                 , t9.req_operador_bastidoramt
                 , t10.item_sellprice_netto
            from prod_worker_ot t1 
               inner join prod_agenda t2 ON t1.wok_ag_id = t2.id 
               inner join prod_worker_init t3 ON t1.wok_init_id = t3.id 
               inner join prod_header t4 ON t2.ag_prdid = t4.id 
               left outer join equipo t7 ON t3.win_equipoid = t7.id 
               left outer join equipo_type t8 ON t7.equipo_type_id = t8.id 
               inner join orders t9 ON t2.ag_reqid = t9.id 
               inner join orders_items t10 ON t9.id = t10.req_id 
               inner join item t11 ON t10.item_id = t11.id 
               left outer join customer t12 ON t9.req_cust_id = t12.id 
               inner join item_productcats ip1 on ip1.item_id = t11.id 
               inner join productcats p1 on ip1.cat_id = p1.id 
            where t7.equipo_type_id = 11 and t7.id = 36
              and ( select count(*) 'cc' from prod_worker_ot_autocontrol t99 where t99.ctr_init_id = t1.id ) > 0 
              and t1.wok_status > 0
              and t1.wok_crtdat between {$sql_datefrom} and {$sql_dateto} ";

if($_SESSION[$_sesmodulename]["sql_number"] != "")
   $sql .= " and t9.req_number = '{$_SESSION[$_sesmodulename]["sql_number"]}' ";
//----------------------------------------------------------------------------------

if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $sql .= " and req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
 
// $itemcount = $CON->select($sql);
// $itemcount = count($itemcount);
// $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

$sql .=" order by t9.id, t1.wok_crtdat";
// $sql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";
// echo($sql);

$data = $CON->select($sql);

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
   <td height="30"><b class="content_header">Informe de Pulpo Serigrafico</b></td>
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
         <td class="content_rowl"><nobr>Periodo de Produccion *</nobr></td>
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
                  <input type="text" style="width:65px" id="sql_datefrom" name="sql_datefrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_datefrom"]?>"> 
                  -
                  <input type="text" style="width:65px" id="sql_dateto" name="sql_dateto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_dateto"]?>">
                  </nobr>   
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Nro CC</td>
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
      <?php
        //  printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
      ?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td colspan="27" class="content_row_os" align="center">Informacion C.C.</td>
      </tr>
      <tr>
         <td  class="content_rowl content_row_os" align="center">1</td>
         <td  class="content_rowl content_row_os" align="center">2</td>
         <td  class="content_rowl content_row_os" align="center">3</td>
         <td  class="content_rowl content_row_os" align="center">4</td>
         <td  class="content_rowl content_row_os" align="center">5</td>
         <td  class="content_rowl content_row_os" align="center">6</td>
         <td  class="content_rowl content_row_os" align="center">7</td>
         <td  class="content_rowl content_row_os" align="center">8</td>
         <td  class="content_rowl content_row_os" align="center">9</td>
         <td  class="content_rowl content_row_os" align="center">10</td>
         <td  class="content_rowl content_row_os" align="center">11</td>
         <td  class="content_rowl content_row_os" align="center">12</td>
         <td  class="content_rowl content_row_os" align="center">13</td>
         <td  class="content_rowl content_row_os" align="center">14</td>
         <td  class="content_rowl content_row_os" align="center">15</td>
         <td  class="content_rowl content_row_os" align="center">16</td>
         <td  class="content_rowl content_row_os" align="center">17</td>
         <td  class="content_rowl content_row_os" align="center">18</td>
         <td  class="content_rowl content_row_os" align="center">19</td>
         <td  class="content_rowl content_row_os" align="center">20</td>
         <td  class="content_rowl content_row_os" align="center">21</td>
         <td  class="content_rowl content_row_os" align="center">22</td>
         <td  class="content_rowl content_row_os" align="center">23</td>
         <td  class="content_rowl content_row_os" align="center">24</td>
         <td  class="content_rowl content_row_os" align="center">25</td>
         <td  class="content_rowl content_row_os" align="center">26</td>
         <td  class="content_rowl content_row_os" align="center">27</td>
         <td  class="content_rowl content_row_os" align="center">28</td>
         <td  class="content_rowl content_row_os" align="center">29</td>
         <td  class="content_rowl content_row_os" align="center">30</td>
         <td  class="content_rowl content_row_os" align="center">31</td>
         <td  class="content_rowl content_row_os" align="center">32</td>
         <td  class="content_rowl content_row_os" align="center">33</td>
         <td  class="content_rowl content_row_os" align="center">34</td>
         <td  class="content_rowl content_row_os" align="center">35</td>
         <td  class="content_rowl content_row_os" align="center">36</td>
         <td  class="content_rowl content_row_os" align="center">37</td>
         <td  class="content_rowl content_row_os" align="center">38</td>
         <td  class="content_rowl content_row_os" align="center">39</td>
         <td  class="content_rowl content_row_os" align="center">40</td>
         <td  class="content_rowl content_row_os" align="center">41</td>
         <td  class="content_rowl content_row_os" align="center">42</td>
         <td  class="content_rowl content_row_os" align="center">43</td>
         <td  class="content_rowl content_row_os" align="center">44</td>
         <td  class="content_rowl content_row_os" align="center">45</td>
         <td  class="content_rowl content_row_os" align="center">46</td>
         <td  class="content_rowl content_row_os" align="center">47</td>
         <td  class="content_rowl content_row_os" align="center">48</td>
         <td  class="content_rowl content_row_os" align="center">49</td>
         <td  class="content_rowl content_row_os" align="center">50</td>
         <td  class="content_rowl content_row_os" align="center">51</td>
         <td  class="content_rowl content_row_os" align="center">52</td>
         <td  class="content_rowl content_row_os" align="center">53</td>
         <td  class="content_rowl content_row_os" align="center">54</td>
         <td  class="content_rowl content_row_os" align="center">55</td>
         <td  class="content_rowl content_row_os" align="center">56</td>
         <td  class="content_rowl content_row_os" align="center">57</td>
         <td  class="content_rowl content_row_os" align="center">58</td>
         <td  class="content_rowl content_row_os" align="center">59</td>
         <td  class="content_rowl content_row_os" align="center">60</td>
         <td  class="content_rowl content_row_os" align="center">61</td>
         <td  class="content_rowl content_row_os" align="center">62</td>
         <td  class="content_rowl content_row_os" align="center">63</td>
         <td  class="content_rowl content_row_os" align="center">64</td>
         <td  class="content_rowl content_row_os" align="center">65</td>
         <td  class="content_rowl content_row_os" align="center">66</td>
         <td  class="content_rowl content_row_os" align="center">67</td>
         <td  class="content_rowl content_row_os" align="center">68</td>
         <td  class="content_rowl content_row_os" align="center">69</td>
         <td  class="content_rowl content_row_os" align="center">70</td>
         <td  class="content_rowl content_row_os" align="center">71</td>
         <td  class="content_rowl content_row_os" align="center">72</td>
         <td  class="content_rowl content_row_os" align="center">73</td>
         <td  class="content_rowl content_row_os" align="center">74</td>
         <td  class="content_rowl content_row_os" align="center">75</td>
         <td  class="content_rowl content_row_os" align="center">76</td>
         <td  class="content_rowl content_row_os" align="center">77</td>
         <td  class="content_rowl content_row_os" align="center">78</td>
         <td  class="content_rowl content_row_os" align="center">79</td>
         <td  class="content_rowl content_row_os" align="center">80</td>
         <td  class="content_rowl content_row_os" align="center">81</td>
         <td  class="content_rowl content_row_os" align="center">82</td>
         <td  class="content_rowl content_row_os" align="center">83</td>
         <td  class="content_rowl content_row_os" align="center">84</td>
         <td  class="content_rowl content_row_os" align="center">85</td>
         <td  class="content_rowl content_row_os" align="center">86</td>
         <td  class="content_rowl content_row_os" align="center">87</td>
         <td  class="content_rowl content_row_os" align="center">88</td>
         <td  class="content_rowl content_row_os" align="center">89</td>
         <td  class="content_rowl content_row_os" align="center">90</td>
         <td  class="content_rowl content_row_os" align="center">91</td>
         <td  class="content_rowl content_row_os" align="center">92</td>
         <td  class="content_rowl content_row_os" align="center">93</td>
         <td  class="content_rowl content_row_os" align="center">94</td>
         <td  class="content_rowl content_row_os" align="center">95</td>
         <td  class="content_rowl content_row_os" align="center">96</td>
         <td  class="content_rowl content_row_os" align="center">97</td>
         <td  class="content_rowl content_row_os" align="center">98</td>
         <td  class="content_rowl content_row_os" align="center">99</td>
         <td  class="content_rowl content_row_os" align="center">100</td>
         <td  class="content_rowl content_row_os" align="center">101</td>
         <td  class="content_rowl content_row_os" align="center">102</td>
         <td  class="content_rowl content_row_os" align="center">103</td>
         <td  class="content_rowl content_row_os" align="center">104</td>
         <td  class="content_rowl content_row_os" align="center">105</td>
      </tr>
      <tr>

<!-- 01 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Inicio  CC</td>
<!-- 02 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Produccion</td>
<!-- 03 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Número de CC</td>
<!-- 04 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Cantidad de Bolsas</td>
<!-- 05 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Numero de OT</td>
<!-- 06 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Cliente</td>
<!-- 07 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Tipo Bolsa</td>
<!-- 08 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Formato Bolsa</td>
<!-- 09 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Codigo Producto</td>
<!-- 10 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Descripcion Producto</td>
<!-- 11 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Corte de Bolsa</td>
<!-- 12 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">UM</td>
<!-- 13 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Ancho de Bolsa</td>
<!-- 14 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Alto Frente de Bolsa</td>
<!-- 15 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Alto Dorso de Bolsa</td>
<!-- 16 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Medida Doblez Superior</td>
<!-- 17 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Medida Fuelle de Bolsa</td>
<!-- 18 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Largo Manilla</td>
<!-- 19 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color Tela</td>
<!-- 20 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Código Color Tela</td>
<!-- 21 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Gramaje</td>
<!-- 22 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Ancho Bobina</td>
<!-- 23 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Código de Tela</td>
<!-- 24 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Cantidad de Bobinas</td>
<!-- 25 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Pie de Imprenta</td>
<!-- 26 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Códgo de Barra</td>
<!-- 27 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Número Código de Barra</td>
<!-- 28 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Alarma</td>
<!-- 29 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Número de Alarma</td>
<!-- 30 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Nombre Máquina</td>
<!-- 31 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Numero de Máquina</td>
<!-- 32 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Nombre Supervisor</td>
<!-- 33 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Rut supervisor</td>
<!-- 34 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Hora Inicio (Alistamiento)</td>
<!-- 35 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Hora Término (Alistamiento)</td>
<!-- 36 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Estado de Alistamiento</td>
<!-- 37 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Horas (Alistamiento)</td>
<!-- 38 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Hora Inicio (Producción)</td>
<!-- 39 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Hora Término (Producción)</td>
<!-- 40 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Estado de Producción</td>
<!-- 41 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Hora Producción</td>
<!-- 42 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Turno (mañana-tarde-noche)</td>
<!-- 43 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Horas Turno</td>
<!-- 44 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Nombre Operador</td>
<!-- 45 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Rut Operador</td>
<!-- 46 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Nombre Ayudante</td>
<!-- 47 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Rut Ayudante</td>
<!-- 48 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">% Merma Programado</td>
<!-- 49 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Impresiones al Desarrollo Programado</td>
<!-- 50 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total programado impresora (unidades)</td>
<!-- 51 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Programado Impresora (Pasadas/turno)</td>
<!-- 52 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Programado Impresora (Metros/lineales)</td>
<!-- 53 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Programado Impresora (Kgs)</td>
<!-- 54 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Produccion Esperadas (Unidades)</td>
<!-- 55 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Cantidad Colores a Imprimir</td>
<!-- 56 -->         <td rowspan="2" class="content_rowl content_row_os" align="center"><nobr>Color Impreso</nobr></td>                               
<!-- 57 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Impresa Terminadas (Un)</td> 
<!-- 58 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Impresiones del Desarrollo Producido</td>
<!-- 59 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total producido impresora (unidades)</td>
<!-- 60 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Producido Impresora (Pasadas/turno)</td>
<!-- 61 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Producido Impresora (Metros/Lineales)</td>
<!-- 62 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Producido Impresora (Kgs)</td>
<!-- 63 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Eficiencia Productiva (%)</td>
<!-- 64 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Velocidad Máquina (Metros x Minuto)</td>
<!-- 65 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Peso Unitario (Kgs)</td>
<!-- 66 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">N° Bastidor Utilizado</td>
<!-- 67 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Impresiones Al Ancho</td>
<!-- 68 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Ancho Bobina</td>
<!-- 69 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color Bobina</td>
<!-- 70 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Codigo Color Bobina</td>
<!-- 71 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Gramaje Bobina</td>
<!-- 72 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Estado Bobiba (Nueva-Usada)</td>
<!-- 73 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">N° Bobinas Procesadas (Unidades)</td>
<!-- 74 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Kilos Bobinas Procesadas (Kgs)</td>
<!-- 75 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Metros Lineales Bobinas Procesadas (Mts/Lineales)</td>
<!-- 76 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Merma Total Kilos</td>
<!-- 77 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Merma (%)</td>
<!-- 78 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color 1 Frente</td>
<!-- 79 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color 2 Frente</td>
<!-- 80 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color 3 Frente</td>
<!-- 81 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color 4 Frente</td>
<!-- 82 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color 1 Dorso</td>
<!-- 83 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color 2 Dorso</td>
<!-- 84 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color 3 Dorso</td>
<!-- 85 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Color 4 Dorso</td>
<!-- 86 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Kgs Tinta Utilizadas (color 1)</td>
<!-- 87 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Kgs Tinta Utilizadas (color 2)</td>
<!-- 88 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Kgs Tinta Utilizadas (color 3)</td>
<!-- 89 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Kgs Tinta Utilizadas (color 4)</td>
<!-- 90 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Bobina Sobrante (Kgs)</td>
<!-- 91 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Merma Alistamiento (UN)</td>
<!-- 92 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Merma Alistamiento (Kgs)</td>
<!-- 93 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Merma impresion (Un)</td>
<!-- 94 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Merma Impresion (Kgs)</td>
<!-- 95 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Merma Bobina Defectuosa (Un)</td>
<!-- 96 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Merma Bobina Defectuosa (Kgs)</td>
<!-- 97 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Tiempo Productivo (Horas)</td>
<!-- 98 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Total Paros (Horas)</td>
<!-- 99 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Tiempo Neto Productivo (Horas)</td>
<!-- 100 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Disponibilidad (%)</td>
<!-- 101 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Rendimiento (%)</td>
<!-- 102 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Calidad (%)</td>
<!-- 103 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Oee (%)</td>
<!-- 104 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">$ Producidos</td>
<!-- 105 -->         <td rowspan="2" class="content_rowl content_row_os" align="center">Observación Producción</td>

      </tr>
      <tr>
      </tr>
      <?php
      for($x = 0; $x < count($data) && $data != false; $x++)
      {         
         /* Carga datos para la agenda */

         $sql = " select distinct t0.*,
                  t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                  t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                  t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id 'prdid',
                  t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                  t1x.fab_print_height, v1.add_name 'fabric_color', v2.add_name 'manilla_color',
                  fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4,fab_print_colors_front_5,
                  fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                  fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                  t3x.id 'prdid', t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                  t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4, 
                  t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
                  t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,
                  t1.req_solic_devprints_cc, t1x.fab_design_name, t1x.fab_mat_gramms,
                  t1.req_operador_bastidoramt, t1.req_operador_mermaperc, t1.req_operador_tela_width,
                  t1.req_solic_devprints_poltype, t1x.fab_manilla_length, t1.req_rebo_type,
                  t1.req_rebo_state, t1.req_rebo_rolloscc, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cortescc
         from prod_agenda t0
         INNER JOIN prod_header t3x       ON t0.ag_prdid = t3x.id and t3x.prd_status >= 2
         INNER JOIN orders t1             ON t0.ag_reqid = t1.id
         LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
         LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
         LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
         INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
         INNER JOIN item t2x              ON t1x.item_id = t2x.id
         LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
         LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
         where
         t0.id = {$data[$x]["wok_ag_id"]}";
         $agenda = $CON->select($sql);
         $agenda = $agenda[0];

         /* Fin carga datos de Agenda */

         /* Buscamos datos del producto */

         $sql = "select i.id
                  ,i.item_number_prod
                  ,i.item_title
                  ,tc.id
                  ,tc.com_name
                  ,tciv.val_id
                  ,t3.add_name
                  ,item_sellprice_brutto
               from item i
                  inner join tran_comments_item_vals tciv on tciv.item_id = i.id
                  inner join tran_comments tc on tciv.com_id = tc.id
                  inner join tran_comments_vals t3 on t3.id = tciv.val_id 
                  where i.item_number_prod = '{$data[$x]["codigo_producto"]}'
                  ";

         $cat_productos = $CON->select($sql);
         
         foreach($cat_productos AS $cat_producto)
         {
            if($cat_producto[id]==31) // Ancho Bolsa 
               $data[$x]["ancho_bolsa"] = $cat_producto["add_name"];
            if($cat_producto[id]==35) // fuelle bolsa
               $data[$x]["medida_fuelle_bolsa"] = $cat_producto["add_name"];
            if($cat_producto[id]==37) // largo manilla
               $data[$x]["largo manilla"] = $cat_producto["add_name"];
            if($cat_producto[id]==39) // alto frente
               $data[$x]["alto_frente_bolsa"] = $cat_producto["add_name"];
            if($cat_producto[id]==40) // alto dorso
               $data[$x]["alto_dorso_bolsa"] = $cat_producto["add_name"];
            if($cat_producto[id]==41) // doblez superior
               $data[$x]["medida_doblez_superior"] = $cat_producto["add_name"];
            
         }

         /*
         $sql = " select *
         from prod_worker_ot_events
         where
         id = {$_REQUEST["refid"]}";
         $refevent = $CON->select($sql);
         $refevent = $refevent[0];
         */

         // $events        = getProdEvents($CON, $agenda["prdid"], 0, 0, 0, 0, $agenda["ag_equipotype_id"]); 


         if((int)$data[$x]["item_prodcalc_fuelle_act"])
            $param_medida = (int)$data[$x]["ancho_bolsa"] + (int)$data[$x]["medida_fuelle_bolsa"];
         else
            $param_medida = (int)$data[$x]["ancho_bolsa"];

         $sql = " select concat(t2.user_firstname,' ',t2.user_lastname) as supervisor
                        ,t2.user_rut       as rutsupervisor
                        ,t2.id
                     from prod_worker_ot_autocontrol t1
                        inner join user t2 ON t1.ctr_ctrusr = t2.id
                     where t1.ctr_init_id = {$data[$x]["id"]} and
                        t1.ctr_type = 'supervisor'
                     order by ctr_ctrdat desc
                  limit 0,1";
         $supervisores = $CON->select($sql);
         $supervisores = $supervisores[0];

       
         $sql = " select concat(t2.user_firstname,' ',t2.user_lastname) as user_lastname 
                       , t3.wrk_rut as user_rut
                       , MAX(ctr_ctrdat) 'ctr_ctrdat'
                       , t2.id
                       , t3.id as IdWorker
                     from prod_worker_ot_autocontrol t1
                        INNER JOIN user t2 ON t1.ctr_ctrusr = t2.id
                        inner join workers t3 on t2.id = t3.wrk_uid
                  where t1.ctr_init_id = {$data[$x]["id"]} and
                     t1.ctr_type    = 'worker'
                  group by 1
                  LIMIT 0,1";
         $operador = $CON->select($sql);
         $operador = $operador[0];

         if((int)$data[$x]["item_prodcalc_fuelle_act"])
            $param_medida = (int)$data[$x]["ancho_bolsa"] + (int)$data[$x]["medida_fuelle_bolsa"];
         else
            $param_medida = (int)$data[$x]["ancho_bolsa"];

         $sql = " select *
                  from equipo_params
                  where
                  param_equipo_id = {$data[$x]["win_equipoid"]} and
                  param_medida    >= {$param_medida}
                  order by param_medida asc
                  LIMIT 0,1";
         $equipo_params = $CON->select($sql);
         $equipo_params = $equipo_params[0];

         if((int)$data[0]["equipo_prod_isprinter_seri"])
         {
            $corte_m2 = (float)$equipo_params["param_corte"];
            $corte_z  = (int)$equipo_params["param_z"];

         }
         elseif((int)$data[0]["equipo_prod_isprinter_flexo"])
         {
            
            if($data[$x]["req_solic_devprints_poltype"] == "pol284")
            {
               $corte_m2 = (float)$equipo_params["param_poly28"];
               $corte_z  = (int)$equipo_params["param_z"];
            }
            elseif($data[$x]["req_solic_devprints_poltype"] == "pol170")
            {
               $corte_m2 = (float)$equipo_params["param_poly17"];
               $corte_z  = (int)$equipo_params["param_z"];
            }

         }
         elseif($_ISSELLADORA)
         {
            $sql = " select t4.*, t5.mant_title, t7.req_number, t1.prd_number, t8.cust_name,
                           t2x.item_number_prod, t2x.item_title, t5x.pause_name, t5x.pause_code,
                           t5.mant_code, t2.ag_equipotype_id, t2.ag_prdid, t1x.item_amount,
                           t6.win_equipoid
                     from prod_header t1
                     INNER JOIN prod_agenda t2           ON t1.id = t2.ag_prdid
                     INNER JOIN prod_worker_ot t3        ON t3.wok_ag_id = t2.id
                     INNER JOIN prod_worker_ot_events t4 ON t4.evt_prod_worker_otid = t3.id
                     LEFT OUTER JOIN equipo_manttype t5  ON t4.evt_equipo_mantid = t5.id
                     INNER JOIN prod_worker_init t6      ON t3.wok_init_id = t6.id
                     INNER JOIN orders t7                ON t2.ag_reqid = t7.id
                     LEFT OUTER JOIN customer t8         ON t7.req_cust_id = t8.id
                     INNER JOIN orders_items t1x         ON t7.id = t1x.req_id
                     INNER JOIN item t2x                 ON t1x.item_id = t2x.id
                     LEFT OUTER JOIN prod_pause_types t5x ON t4.evt_pause_id = t5x.id
                     INNER JOIN equipo t6x               ON t6.win_equipoid = t6x.id
                     where
                     t1.prd_reqid   = {$hasopenot["prd_reqid"]} and
                     t1.prd_status  = 2 and
                     t2.ag_status   > 0 and
                     t3.wok_status  > 0 and
                     t4.evt_status  > 0 and
                     t6.win_status  > 0
                     order by t4.evt_crtdat desc
                     LIMIT 0,1";
            $lastprodevent = $CON->select($sql);
            $lastprodevent = $lastprodevent[0];

            $sql = " select *
                     from equipo_params
                     where
                     param_equipo_id = {$data[$x]["win_equipoid"]} and
                     param_medida    >= {$param_medida}
                     order by param_medida asc
                     LIMIT 0,1";
            $equipo_params = $CON->select($sql);
            $equipo_params = $equipo_params[0];

            if($agenda["fab_printtype"] == "SERI")
            {
               $corte_m2 = (float)$equipo_params["param_corte"];
               $corte_z  = (int)$equipo_params["param_z"];
            }
            else
            {
               if($data[$x]["req_solic_devprints_poltype"] == "pol284")
               {
                  $corte_m2 = (float)$equipo_params["param_poly28"];
                  $corte_z  = (int)$equipo_params["param_z"];
               }
               elseif($data[$x]["req_solic_devprints_poltype"] == "pol170")
               {
                  $corte_m2 = (float)$equipo_params["param_poly17"];
                  $corte_z  = (int)$equipo_params["param_z"];
               }
            }
         }
      
         $time_diff  = $data[$x]["fecha_hora_t_alistamiento"] - $data[$x]["fecha_hora_i_alistamiento"];
         $time_diffx = $time_diff / 60;
         $hours_diff = (int)($time_diffx / 60);
         $min_diff   = (int)($time_diffx - ($hours_diff * 60));

         $colorfron[] = '';
         $colorback[] = '';
         $k_color     = 0;

         for($xx = 1; $xx <= 4; $xx++)
         {
            if((int)$data[$x]["fab_print_colors_front_{$xx}"])
            {
               $colorfron[$xx] = $data[$x]["fab_print_colordesc_{$xx}"];
               $k_color ++;
            }
            else
               $colorfron[$xx] = 'Sin Información';

            if((int)$data[$x]["fab_print_colors_back_{$xx}"])
            {
               $colorback[$xx] = $data[$x]["fab_print_colordesc_{$xx}"];
               $k_color ++;
            }
            else
               $colorback[$xx] = 'Sin Información';
         }
        
          $sql = " select t2.user_lastname, MAX(ctr_ctrdat) 'ctr_ctrdat'
                     from prod_worker_ot_autocontrol t1
                           INNER JOIN user t2 ON t1.ctr_ctrusr = t2.id
                     where t1.ctr_init_id = {$data[$x]["id"]} and
                        t1.ctr_type    = 'worker'
                     group by 1
                     LIMIT 0,1";
         $wdata = $CON->select($sql);
         $wdata = $wdata[0];

         $apertura_ini = "";
         $apertura_fin = "";
         $apertura_dif = "";
         $estado_apert = "";

         if((int)$data[$x]["wok_crtdat"])
         {
            $apertura_ini   = date("d.m.Y H:i:s",$data[$x]["wok_crtdat"]); // date("d.m.Y H:i:s",$lastprodevent["evt_crtdat"]);
            $apertura_fin   = date("d.m.Y H:i:s",$wdata["ctr_ctrdat"]);
            $time_diff_2    = $wdata["ctr_ctrdat"] - $data[$x]["wok_crtdat"];
            if( (int)$wdata["ctr_ctrdat"] )
            {
               $estado_apert   = "Terminada";
               $apertura_fin   = $wdata["ctr_ctrdat"];
            }
            else
            {
               $estado_apert = "En Curso";
               $apertura_fin = time();
            }
            $time_diff_2    = $apertura_fin - $data[$x]["wok_crtdat"];
            $time_diffx_2   = $time_diff_2 / 60;
            $a_hours_diff_2 = (int)($time_diffx_2 / 60);
            $a_min_diff_2   = (int)($time_diffx_2 - ($a_hours_diff_2 * 60));
            $apertura_fin   = date("d.m.Y H:i:s",$apertura_fin);
            $apertura_dif   = substr('00'.$a_hours_diff_2,-2).":".substr('00'.$a_min_diff_2,-2);
         }

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

         $prod_ini = "";
         $prod_fin = "";
         $prod_dif = "";

         $fecha_ot =  date("Y-m-d",$sdata["ctr_ctrdat"]);

         $posdata    = getOrderPos($CON, $data[$x]["IdCC"]);
         $thispos    = $posdata[0];
         $sql = " select add_name, add_name_eng
                  from tran_comments_vals
                  where id = {$thispos["fab_mat_fabric_color"]}";
         $fabric_color = $CON->select($sql);
         $fabric_color = $fabric_color[0];


         $data[$x]['ancho_bobina'] = $data[$x]["alto_frente_bolsa"] + $data[$x]["alto_dorso_bolsa"] + ($data[$x]["medida_doblez_superior"] * 2) + $data[$x]["medida_fuelle_bolsa"];

         $metros_bobina = 0;
         if ($data[$x]["Codigo_Tela"] == 'PP' || $data[$x]["Codigo_Tela"] == 'TNT')
             $metros_bobina = 1100;

         if ($data[$x]["Codigo_Tela"] == 'PLA')
         {

            if($thispos["fab_mat_gramms"] == '70')
                $metros_bobina = 1950;

           if($thispos["fab_mat_gramms"] == '55')
                $metros_bobina = 2100;

            if($thispos["fab_mat_gramms"] == '80' || $thispos["fab_mat_gramms"] == '85')
                $metros_bobina = 1400;

            if($thispos["fab_mat_gramms"] == '90')
                $metros_bobina = 1250;

         }

         // $data[$x]["cantidad_bobina"] = ($corte_m2 * $data[$x]["cantidad_bolsas"]) / $metros_bobina;

         $mlin_prog = round($agenda["ag_amount"] * $corte_m2);                                  // Metros lineales programados
         $mlin_addi = round($mlin_prog / 100 * (float)$agenda["req_operador_mermaperc"]);       // Metros lineales adicionales a procesar
         $mlin_print = round($mlin_prog + $mlin_addi);                                          // Total metros lineales a imprimi

         

         $mlin_maquina = round($mlin_print / (float)$data[$x]["equipo_prod_divisor_perc"]);     // Total mts/máquina

         if($data[$x]["win_equipoid"]==33)
            $mlin_maquina = $mlin_maquina / 0.41;

         $data[$x]["cantidad_bobina"] = $mlin_print / $metros_bobina;

         /* Datos de produccion */
         $sql = "select pwoe.*
                   from prod_agenda pa
                      inner join prod_worker_ot pwo         on pwo.wok_ag_id = pa.id
                      inner join prod_worker_ot_events pwoe on evt_prod_worker_otid = pwo.id
                 where pa.ag_reqid = {$data[$x]["IdCC"]}
                    and DATE_FORMAT(DATE_ADD( '1970-01-01', INTERVAL evt_crtdat SECOND),'%Y-%m-%d') = '{$fecha_ot}'
                    and pa.ag_equipo_id = {$data[$x]["win_equipoid"]}
                    and pwoe.evt_prod_worker_otid = {$data[$x]["id"]}";
         //           and evt_type = 'prod'";

         $produccion = $CON->select($sql);
         $diferencia_pausa = '00:00';
         $idproduccion     = 0;
         $_RET["_PAUSE_TIME_STR"]   = ' ';
         $_RET["_PAUSE_AMOUNT"]     = 0;
         $_RET["_PAUSE_TIME_MINS"]  = 0;
         
         $_RET["_PRODU_TIME_STR"]   = ' ' ;
         $_RET["_PRODUC_AMOUNT"]    = 0;
         $_RET["_PRODUC_TIME_MINS"] = 0;
         
         $produccion_evt_amount_metros_maquina  = 0;
         $produccion_evt_amount_metros_lineales = 0;
         $minutospause                          = 0;

         $time_diff_p  = 0;
         $time_diffx_p = 0;
         $hours_diff_p = 0;
         $min_diff_p   = 0;

         $time_diff_turno  = 0;
         $time_diffx_turno = 0;
         $hours_diff_turno = 0;
         $min_diff_turno   = 0;
         $jornadas["type_name"] = '';
         $prod_dif = "";
         $estado_prod = "No Iniciado";

         $color1 = 'Sin Informacion';
         $color2 = 'Sin Informacion';
         $color3 = 'Sin Informacion';
         $color4 = 'Sin Informacion';



         for($conprod = 0; $conprod < count($produccion) && $produccion != false; $conprod++)
         {

            if($produccion[$conprod]["evt_type"] == 'prodsericolor' )
            {

               $color1    = $produccion[$conprod]["prod_seri_color"];
               $k_x_color = $produccion[$conprod]["evt_amount"];
               /********************************************/

               $prod_ini       = date("d.m.Y H:i:s",$sdata["ctr_ctrdat"]);
               $dia = date("d",$sdata["ctr_ctrdat"]);
               $mes = date("m",$sdata["ctr_ctrdat"]);
               $ano = date("Y",$sdata["ctr_ctrdat"]);
               $prod_dif = "";

               if((int)$produccion[$conprod]["evt_enddat"])
               {
                  $prod_fin  = date("d.m.Y H:i:s",$produccion[$conprod]["evt_enddat"]);
                  $horafin   = $produccion[$conprod]["evt_enddat"];
                  $estado_prod = "Terminada";
               }
               else
               {
                  $prod_fin  = date("d.m.Y H:i:s",time());
                  $horafin   = time();
                  $estado_prod = "En Curso";
               }
            
               $time_diff_2    = $horafin - $sdata["ctr_ctrdat"];
               $time_diffx_2   = $time_diff_2 / 60;
               $a_hours_diff_2 = (int)($time_diffx_2 / 60);
               $a_min_diff_2   = (int)($time_diffx_2 - ($a_hours_diff_2 * 60));
               $prod_dif       = substr('00'.$a_hours_diff_2,-2).":".substr('00'.$a_min_diff_2,-2);
   
               $sql = "select type_name
                             ,type_name_short 
                        from turnos_config_assign tca
                           inner join turnos_types tt on tt.id = tca.assign_turno_type_id
                           where assign_worker_id = 19 
                           and assign_year = {$ano}
                           and assign_month = {$mes}
                           and assign_day = {$dia}";
   
               $jornadas = $CON->select($sql);
               $jornadas = $jornadas[0];
               /*******************************************/
         
               /*
               $idproduccion = $produccion[$conprod]["id"];
               $produccion_prod_bobina_kg = $produccion[$conprod]["prod_bobina_kg"];
               $Totalproducidoimpresoraunidades = $produccion[$conprod]["evt_amount"];
               */

               $time_diff_p  = $produccion[$conprod]["evt_enddat"] - $produccion[$conprod]["evt_crtdat"];
               $time_diffx_p = $time_diff_p / 60;
               $hours_diff_p = (int)($time_diffx_p / 60);
               $min_diff_p   = (int)($time_diffx_p - ($hours_diff_p * 60));
               $_RET["_PRODUC_AMOUNT"]++;
               $_RET["_PRODUC_TIME_MINS"] += $time_diffx_p;

               if( $produccion[$conprod]["evt_enddat"] > 0)
                  $time_diff_turno = $produccion[$conprod]["evt_enddat"]  - $data[$x]["wok_crtdat"];
               else
                  $time_diff_turno = time() - $data[$x]["wok_crtdat"];

               $time_diffx_turno = $time_diff_turno / 60;
               $hours_diff_turno = (int)($time_diffx_turno / 60);
               $min_diff_turno   = (int)($time_diffx_turno - ($hours_diff_turno * 60));
               $jornadas["type_name"] = substr('00'.$hours_diff_turno,-2).":".substr('000'.$min_diff_turno,-2);
               $minutos_turnos = ($hours_diff_turno * 60) + $min_diff_turno;
               $comentario_prod = $produccion[$conprod]["evt_comments"];
            }

            if($produccion[$conprod]["evt_type"] == 'prod' )
            {

               /********************************************/

               $prod_ini       = date("d.m.Y H:i:s",$sdata["ctr_ctrdat"]);
               $dia = date("d",$sdata["ctr_ctrdat"]);
               $mes = date("m",$sdata["ctr_ctrdat"]);
               $ano = date("Y",$sdata["ctr_ctrdat"]);
               $prod_dif = "";

               if((int)$data[$x]["wok_enddat"])
               {
                  $prod_fin  = date("d.m.Y H:i:s",$data[$x]["wok_enddat"]);
                  $horafin   = $data[$x]["wok_enddat"];
                  $estado_prod = "Terminada";
               }
               else
               {
                  $prod_fin  = date("d.m.Y H:i:s",time());
                  $horafin   = time();
                  $estado_prod = "En Curso";
               }
            
               $time_diff_2    = $horafin - $sdata["ctr_ctrdat"];
               $time_diffx_2   = $time_diff_2 / 60;
               $a_hours_diff_2 = (int)($time_diffx_2 / 60);
               $a_min_diff_2   = (int)($time_diffx_2 - ($a_hours_diff_2 * 60));
               $prod_dif       = substr('00'.$a_hours_diff_2,-2).":".substr('00'.$a_min_diff_2,-2);
   
               $sql = "select type_name
                             ,type_name_short 
                        from turnos_config_assign tca
                           inner join turnos_types tt on tt.id = tca.assign_turno_type_id
                           where assign_worker_id = 19 
                           and assign_year = {$ano}
                           and assign_month = {$mes}
                           and assign_day = {$dia}";
   
               $jornadas = $CON->select($sql);
               $jornadas = $jornadas[0];
               /*******************************************/
         

               $idproduccion = $produccion[$conprod]["id"];
               $produccion_prod_bobina_kg = $produccion[$conprod]["prod_bobina_kg"];
               $Totalproducidoimpresoraunidades = $produccion[$conprod]["evt_amount"];
               
               /*
               if($produccion[$conprod]["evt_metrotype"]=='metros_lineales')
               {
                  $produccion_evt_amount_metros_maquina  = round($produccion[$conprod]["evt_amount_metros_lineales"] / 0.41,0);
                  $produccion_evt_amount_metros_lineales = round($produccion[$conprod]["evt_amount_metros_lineales"],0);
               }
               else
               {
                  $produccion_evt_amount_metros_maquina  = round($produccion[$conprod]["evt_amount_metros_maquina"],0);
                  $produccion_evt_amount_metros_lineales = round($produccion[$conprod]["evt_amount_metros_maquina"] * 0.41,0);
               }
                  */

               // date("d.m.Y H:i:s",time());
               
              
               // else
               //    $time_diff_p     = time() - $produccion[$conprod]["evt_crtdat"];
               /*
               if( date("y",$data[$x]["wok_crtdat"]) > 2000 )
                  $time_diff_turno = $produccion[$conprod]["evt_enddat"]  - $data[$x]["wok_crtdat"];
               else
                   $time_diff_turno = time()  - $data[$x]["wok_crtdat"];
               */

               $time_diff_p  = $produccion[$conprod]["evt_enddat"] - $produccion[$conprod]["evt_crtdat"];
               $time_diffx_p = $time_diff_p / 60;
               $hours_diff_p = (int)($time_diffx_p / 60);
               $min_diff_p   = (int)($time_diffx_p - ($hours_diff_p * 60));
               $_RET["_PRODUC_AMOUNT"]++;
               $_RET["_PRODUC_TIME_MINS"] += $time_diffx_p;

               if( $produccion[$conprod]["evt_enddat"] > 0)
                  $time_diff_turno = $produccion[$conprod]["evt_enddat"]  - $data[$x]["wok_crtdat"];
               else
                  $time_diff_turno = time() - $data[$x]["wok_crtdat"];

               $time_diffx_turno = $time_diff_turno / 60;
               $hours_diff_turno = (int)($time_diffx_turno / 60);
               $min_diff_turno   = (int)($time_diffx_turno - ($hours_diff_turno * 60));
               $jornadas["type_name"] = substr('00'.$hours_diff_turno,-2).":".substr('000'.$min_diff_turno,-2);
               $minutos_turnos = ($hours_diff_turno * 60) + $min_diff_turno;
               $comentario_prod = $produccion[$conprod]["evt_comments"];
            }
            
            if($produccion[$conprod]["evt_type"] == 'apertura' )
            {
               $time_diff  = $produccion[$conprod]["evt_enddat"] - $produccion[$conprod]["evt_crtdat"];
               $time_diffx = $time_diff / 60;

               $hours_diff_aper = (int)($time_diffx / 60);
               $min_diff_aper   = (int)($time_diffx - ($hours_diff * 60));

               $sql = "select * from workers where id = {$produccion[$conprod]["evt_idayudante"]}";
               $ayudante = $CON->select($sql);
               $ayudante = $ayudante[0];
            }

            if($produccion[$conprod]["evt_type"] == 'pause' )
            {
               $time_diff  = $produccion[$conprod]["evt_enddat"] - $produccion[$conprod]["evt_crtdat"];
               $time_diffx = $time_diff / 60;
               $hours_diff = (int)($time_diffx / 60);
               $min_diff   = (int)($time_diffx - ($hours_diff * 60));
               $_RET["_PAUSE_AMOUNT"]++;
               $_RET["_PAUSE_TIME_MINS"] += $time_diffx;
               $minutospause = ($hours_diff * 60 ) + $min_diff;
            }

         }

         if($prod_dif == "")
         {
            $jornadas["type_name_short"] = " ";
         }
         // $_OT_STATS     = getProdStats($CON, $agenda["prdid"], 0, 0, 0, 0, $$data[$x]["equipo_type_id"]);

         $time_diffx = $_RET["_PAUSE_TIME_MINS"];
         $hours_diff = (int)($time_diffx / 60);
         $min_diff   = (int)($time_diffx - ($hours_diff * 60));
         // $_RET["_PAUSE_TIME_STR"] = "{$hours_diff}h {$min_diff}m";
         $_RET["_PAUSE_TIME_STR"] = substr('00'.$hours_diff,-2).":".substr('000'.$min_diff,-2);

         $time_diffx_p = $_RET["_PRODUC_TIME_MINS"];
         $hours_diff_p = (int)($time_diffx_p / 60);
         $min_diff_p   = (int)($time_diffx_p - ($hours_diff_p * 60));
         $_RET["_PRODU_TIME_STR"] = substr('00'.$hours_diff_p,-2).":".substr('000'.$min_diff_p,-2); // = "{$hours_diff_p}h {$min_diff_p}m"; substr('00'.$a_min_diff_2,-2);

         $peso_unitario = $corte_m2 * $data[$x]["ancho_bobina"] / 100 *  $thispos["fab_mat_gramms"] / 1000;
         $ancho_bobina = ($data[$x]["alto_frente_bolsa"]+$data[$x]["alto_dorso_bolsa"]) + ($data[$x]["medida_doblez_superior"] * 2) + $data[$x]["medida_fuelle_bolsa"] ;

         if( $estado_prod != 'Terminada')
         {
            $_RET["_PRODU_TIME_STR"] = $prod_dif;
         }
         /*
         if($prod_ini == "")
         {
           $prod_ini  = date("d.m.Y 00:00:00",time());
           $prod_fin  = date("d.m.Y 00:00:00",time());
           $prod_dif  = "00:00";
           $_RET["_PRODU_TIME_STR"] = "00:00";
           $jornadas["type_name"] = "00:00";
         }

         if($prod_fin == "")
         {
           $prod_fin  = date("d.m.Y 00:00:00",time());
           $prod_dif  = "00:00";
           $_RET["_PRODU_TIME_STR"] = "00:00";
           $jornadas["type_name"] = "00:00";
         }
         */
         $sql = "select t3.add_name_eng
                       ,t3.add_name
                       ,i.item_reg_gsm
                       ,stk_annotation
                       ,i.item_reg_length
                       ,i.item_reg_kg
                   from stockchanges t1
                     inner join stockchanges_items t2 ON t1.id = t2.stk_id
                     inner join item i on i.id = item_id
                     inner join tran_comments_item_vals tciv on tciv.item_id = i.id
                     inner join tran_comments tc on tciv.com_id = tc.id and tc.id = 17
                     inner join tran_comments_vals t3 on t3.id = tciv.val_id 
                  where sth_fromprodotid = {$data[$x]["id"]}
                  and stk_status > 0";

         $color_bobina    = $CON->select($sql);
         $total_producido = $produccion_evt_amount_metros_lineales * $data[$x]["ancho_bobina"] / 100 * $color_bobina[0]["item_reg_gsm"] / 100;
         $mtslineabobinaprocesada = 0;
         $posicion = 0;
         $suma_largo = 0;
         $suma_kilo = 0;
         for($xx = 0; $xx < count($color_bobina) && $color_bobina != false; $xx++)
         {
            if( (int)strpos($color_bobina[$xx]["stk_annotation"], "Kilogramos") )
               $posicion = $posicion + substr($color_bobina[$xx]["stk_annotation"],0, strpos($color_bobina[$xx]["stk_annotation"], "Kilogramos"));

            $suma_largo = $suma_largo + $color_bobina[$xx]["item_reg_length"];
            $suma_kilo = $suma_kilo + $color_bobina[$xx]["item_reg_kg"];
         }

         $mtslineabobinaprocesada = $posicion * $peso_unitario ;
         
         /* Registros de Mermas */
         $sql = "select p.id as idmerma
                       ,p.evt_amount
                       ,p.evt_crtdat
                       ,p.evt_status
                       ,tm.id as idmerma
                       ,p.evt_kgstounits
                     from prod_worker_ot_defectunits p
                         inner join prod_mermatypes tm on tm.id = evt_merma_typeid
                     where
                     evt_refid = {$idproduccion} and  /* prod_worker_ot_events.id */ 
                     evt_type  = 'merma' and evt_status > 0";
        $mermas = $CON->select($sql);

        $merma_configurar = 0;
        $merma_otros  = 0;
        $merma_impresion = 0;

         
         for($xxx = 0; $xxx < count($mermas) && $mermas != false; $xxx++)
         {
            if($mermas[$xxx]["idmerma"]=="6")
               $merma_configurar = $mermas[$xxx]["evt_kgstounits"];

            if($mermas[$xxx]["idmerma"]=="7")
               $merma_impresion = $mermas[$xxx]["evt_kgstounits"];

           if($mermas[$xxx]["idmerma"]=="8")
               $merma_otros = $mermas[$xxx]["evt_kgstounits"];
         }

         $total_producido = $total_producido / 10;

         $total_merma = $merma_configurar + $merma_otros + $merma_impresion;
      

         $porce_configuracion = $merma_configurar / $peso_unitario;
         $porce_otros         = $merma_otros / $peso_unitario;
         $porce_impresion     = $merma_impresion / $peso_unitario;

         $Totalprogramadoimpresoraunidades = round($agenda["req_solic_devprints_cc"] * $mlin_maquina,0);
         $produccion_evt_amount_metros_lineales  = $Totalprogramadoimpresoraunidades / $corte_m2;
         $total_producido = $produccion_evt_amount_metros_lineales * $peso_unitario;
         $porce_merma = $total_merma / $total_producido * 100;

         /* Identificar TIPO DE BOLSA */

         $sql = "select t3.add_name_eng
                        ,t3.add_name
                  from item i 
                     inner join tran_comments_item_vals tciv on tciv.item_id = i.id
                     inner join tran_comments tc on tciv.com_id = tc.id and tc.id = 20
                     inner join tran_comments_vals t3 on t3.id = tciv.val_id 
                  where i.id = {$data[$x]["Tipo_Bolsa"]}";
         $tipo_bolsa = $CON->select($sql);
         $tipo_bolsa = $tipo_bolsa[0];

         $velocidad_maquina =  $produccion_evt_amount_metros_lineales / ($minutos_turnos - $minutospause - ($xx * $procesobobina["valor1"]));

         ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <!-- 01 -->     <td class="content_row_os"><nobr><?=date("d.m.Y H:i:s",$data[$x]["fecha_hora_ingreso_cc"])?></nobr></td>  
               <!-- 02 -->     <td class="content_row_os"><nobr><?=date("d.m.Y",$data[$x]["wok_crtdat"])?></nobr></td>  
               <!-- 03 -->     <td class="content_row_os"><nobr><?=$data[$x]["numero_cc"]?></nobr></td>                                  
               <!-- 04 -->     <td class="content_row_os"><nobr><?=printPrice($data[$x]["cantidad_bolsas"])?></nobr></td>                            
               <!-- 05 -->     <td class="content_row_os"><nobr><?=$data[$x]["numeroot"]?></nobr></td>                                   
               <!-- 06 -->     <td class="content_row_os"><nobr><?=$data[$x]["cliente"]?></nobr></td>
               <!-- 07 -->     <td class="content_row_os"><nobr><?=$tipo_bolsa["add_name"]?></nobr></td>
               <!-- 08 -->     <td class="content_row_os"><nobr><?=$data[$x]["formato"]?></nobr></td>
               <!-- 09 -->     <td class="content_row_os"><nobr><?=$data[$x]["codigo_producto"]?></nobr></td>
               <!-- 10 -->     <td class="content_row_os"><nobr><?=$data[$x]["descripcion_producto"]?></nobr></td>
               <!-- 11 -->     <td class="content_row_os"><nobr><?=printPrice($corte_m2, 4)?></nobr></td>                               
               <!-- 12 -->     <td class="content_row_os"><nobr>Mts</nobr></td> 
               <!-- 13 -->     <td class="content_row_os"><nobr><?=printPrice($data[$x]["ancho_bolsa"])?></nobr></td>
               <!-- 14 -->     <td class="content_row_os"><nobr><?=printPrice($data[$x]["alto_frente_bolsa"])?></nobr></td>
               <!-- 15 -->     <td class="content_row_os"><nobr><?=printPrice($data[$x]["alto_dorso_bolsa"])?></nobr></td>
               <!-- 16 -->     <td class="content_row_os"><nobr><?=printPrice($data[$x]["medida_doblez_superior"])?></nobr></td>
               <!-- 17 -->     <td class="content_row_os"><nobr><?=printPrice($data[$x]["medida_fuelle_bolsa"])?></nobr></td>
               <!-- 18 -->     <td class="content_row_os"><nobr><?=printPrice($data[$x]["largo manilla"])?></nobr></td>
               <!-- 19 -->     <td class="content_row_os"><nobr><?=$fabric_color["add_name"]?></nobr></td>
               <!-- 20 -->     <td class="content_row_os"><nobr><?=$fabric_color["add_name_eng"]?></nobr></td>
               <!-- 21 -->     <td class="content_row_os"><nobr><?=printPrice($thispos["fab_mat_gramms"])?></nobr></td>
               <!-- 22 -->     <td class="content_row_os"><nobr><?=$data[$x]["ancho_bobina"]?></nobr></td>
               <!-- 23 -->     <td class="content_row_os"><nobr><?=$data[$x]["Codigo_Tela"]?></nobr></td>
               <!-- 24 -->     <td class="content_row_os"><nobr><?=printPrice($data[$x]["cantidad_bobina"])?></nobr></td>
               <!-- 25 -->     <td class="content_row_os"><nobr><?=$data[$x]["pie_imprenta"]?></nobr></td>
               <!-- 26 -->     <td class="content_row_os"><nobr><?=$data[$x]["codigo_barra"]?></nobr></td>
               <!-- 27 -->     <td class="content_row_os"><nobr><?=$data[$x]["numero_codigo_barra"]?></nobr></td>
               <!-- 28 -->     <td class="content_row_os"><nobr><?=$data[$x]["tien_dispositivo"]?></nobr></td>
               <!-- 29 -->     <td class="content_row_os"><nobr><?=$data[$x]["dispositivo"]?></nobr></td>
               <!-- 30 -->     <td class="content_row_os"><nobr><?=$data[$x]["nombre_maquina"]?></nobr></td>
               <!-- 31 -->     <td class="content_row_os"><nobr><?=$data[$x]["numero_maquina"]?></nobr></td>
               <!-- 32 -->     <td class="content_row_os"><nobr><?=$supervisores["supervisor"]?></nobr></td>
               <!-- 33 -->     <td class="content_row_os"><nobr><?=$supervisores["rutsupervisor"]?></nobr></td>
               <!-- 34  -->     <td class="content_row_os"><nobr><?=$apertura_ini?></nobr></td>
               <!-- 35  -->     <td class="content_row_os"><nobr><?=$apertura_fin?></nobr></td>
               <!-- 36  -->     <td class="content_row_os"><nobr><?=$estado_apert?></nobr></td>
               <!-- 37  -->     <td class="content_row_os"><nobr><?=$apertura_dif?></nobr></td>
               <!-- 38  -->     <td class="content_row_os"><nobr><?=$prod_ini?></nobr></td>
               <!-- 39  -->     <td class="content_row_os"><nobr><?=$prod_fin?></nobr></td>
               <!-- 40  -->     <td class="content_row_os"><nobr><?=$estado_prod?></nobr></td>               
               <!-- 41  -->     <td class="content_row_os"><nobr><?=$prod_dif?></nobr></td>
               <!-- 42  -->     <td class="content_row_os"><nobr><?=$jornadas["type_name_short"]?></nobr></td>
               <!-- 43  -->     <td class="content_row_os"><nobr><?=$jornadas["type_name"]?></nobr></td>  
               <!-- 44  -->     <td class="content_row_os"><nobr ><?=$operador["user_lastname"]?></nobr></td>
               <!-- 45  -->     <td class="content_row_os"><nobr><?=$operador["user_rut"]?></nobr></td>
               <!-- 46  -->     <td class="content_row_os"><nobr><?=$ayudante["wrk_firstname"].' '.$ayudante["wrk_lastname"]?></nobr></td>
               <!-- 47  -->     <td class="content_row_os"><nobr><?=$ayudante["wrk_rut"]?></nobr></td>
               <!-- 48  -->     <td class="content_row_os"><?=printPrice($data[$x]["porcentaje_merma"],2)?></td>
               <!-- 49  -->     <td class="content_row_os"><?=(int)$agenda["req_solic_devprints_cc"]?></td>
               <!-- 50  -->     <td class="content_row_os"><?=printPrice($Totalprogramadoimpresoraunidades)?></td>
               <!-- 51  -->     <td class="content_row_os"><?=printPrice($mlin_maquina)?></td>
               <!-- 52  -->     <td class="content_row_os"><?=printPrice($Totalprogramadoimpresoraunidades * $corte_m2)?></td>  <!-- printPrice(round($mlin_maquina * $corte_m2),0) -->
               <!-- 53  -->     <td class="content_row_os"><?=printPrice($Totalprogramadoimpresoraunidades * $peso_unitario,2)?></td> <!-- printPrice(round($mlin_maquina * $peso_unitario),2) -->
               <!-- 54  -->     <td class="content_row_os"><nobr>Sin Información</nobr></td>
               <!-- 55 -->      <td class="content_row_os"><?=$k_color?></td>
               <!-- 56 -->      <td class="content_row_os"><nobr><?=$color1?></nobr></td>                               
               <!-- 57 -->      <td class="content_row_os"><?=$k_x_color?></td>
               <!-- 58  -->     <td class="content_row_os"><?=$data[$x]["req_operador_bastidoramt"]?></nobr></td>
               <!-- 59  -->     <td class="content_row_os"><?=printPrice($Totalproducidoimpresoraunidades,0)?></td>
               <!-- 60  -->     <td class="content_row_os"><?=printPrice($Totalproducidoimpresoraunidades / $data[$x]["req_operador_bastidoramt"],0)?></td>
               <!-- 61  -->     <td class="content_row_os"><?=printPrice($produccion_evt_amount_metros_lineales,0)?></td>
               <!-- 62  -->     <td class="content_row_os"><?=printPrice($total_producido,0)?></td>
               <!-- 63  -->     <td class="content_row_os"><nobr>Sin Información</nobr></td>
               <!-- 64  -->     <td class="content_row_os"><nobr><?=printPrice($velocidad_maquina,0)?></nobr></td>
               <!-- 65  -->     <td class="content_row_os"><?=printPrice($peso_unitario,4)?></td> 
               <!-- 66  -->     <td class="content_row_os"><nobr>Sin Información</nobr></td>
               <!-- 67  -->     <td class="content_row_os"><?=$data[$x]["req_solic_devprints_poltype"]?></td>
               <!-- 68  -->     <td class="content_row_os"><?=printPrice($ancho_bobina,4)?></td>
               <!-- 69  -->     <td class="content_row_os"><nobr><?=$color_bobina[0]["add_name"]?></nobr></td>
               <!-- 70  -->     <td class="content_row_os"><nobr><?=$color_bobina[0]["add_name_eng"]?></nobr></td>
               <!-- 71  -->     <td class="content_row_os"><?=printPrice($color_bobina[0]["item_reg_gsm"],0)?></td>
               <!-- 72  -->     <td class="content_row_os"><nobr>Sin Información</nobr></td>
               <!-- 73  -->     <td class="content_row_os"><?=$xx?></td>
               <!-- 74  -->     <td class="content_row_os"><?=$posicion?></td>
               <!-- 75  -->     <td class="content_row_os"><?=printPrice($mtslineabobinaprocesada,0)?></td>
               <!-- 76  -->     <td class="content_row_os"><?=printPrice($total_merma,2)?></td>
               <!-- 77  -->     <td class="content_row_os"><?=printPrice($porce_merma,2)?></td>
               <!-- 78  -->     <td class="content_row_os"><nobr><?=$colorfron[1]?></nobr></td>
               <!-- 79  -->     <td class="content_row_os"><nobr><?=$colorfron[2]?></nobr></td>
               <!-- 80  -->     <td class="content_row_os"><nobr><?=$colorfron[3]?></nobr></td>
               <!-- 81  -->     <td class="content_row_os"><nobr><?=$colorfron[4]?></nobr></td>
               <!-- 82  -->     <td class="content_row_os"><nobr><?=$colorback[1]?></nobr></td> 
               <!-- 83  -->     <td class="content_row_os"><nobr><?=$colorback[2]?></nobr></td>
               <!-- 84  -->     <td class="content_row_os"><nobr><?=$colorback[3]?></nobr></td>
               <!-- 85  -->     <td class="content_row_os"><nobr><?=$colorback[4]?></nobr></td>
               <!-- 86  -->     <td class="content_row_os"><nobr>Sin Información</nobr></td>
               <!-- 87  -->     <td class="content_row_os"><nobr>Sin Información</nobr></td>
               <!-- 88  -->     <td class="content_row_os"><nobr>Sin Información</nobr></td>
               <!-- 89  -->     <td class="content_row_os"><nobr>Sin Información</nobr></td>
               <!-- 90  -->     <td class="content_row_os"><?=printPrice($produccion_prod_bobina_kg,2)?></td>
               <!-- 91  -->     <td class="content_row_os"><?=printPrice($porce_configuracion)?></td>
               <!-- 92  -->     <td class="content_row_os"><?=printPrice($merma_configurar,2)?></td>
               <!-- 93  -->     <td class="content_row_os"><?=printPrice($porce_impresion)?></td>
               <!-- 94  -->     <td class="content_row_os"><?=printPrice($merma_impresion,2)?></td>
               <!-- 95  -->     <td class="content_row_os"><?=printPrice($porce_otros)?></td>
               <!-- 96  -->     <td class="content_row_os"><?=printPrice($merma_otros,2)?></td>
               <!-- 97  -->     <td class="content_row_os"><?=$_RET["_PAUSE_TIME_STR"]?></td>
               <!-- 98  -->     <td class="content_row_os"><?=$_RET["_PRODU_TIME_STR"]?></td>
               <!-- 99  -->     <td class="content_row_os"><nobr>Sin informacion</nobr></td>
               <!-- 100  -->     <td class="content_row_os">0</td>
               <!-- 101  -->     <td class="content_row_os">0</td>
               <!-- 102  -->     <td class="content_row_os">0</td>
               <!-- 103  -->     <td class="content_row_os"><?=$data[$x]["item_sellprice_brutto"]?></td>
               <!-- 104  -->     <td class="content_row_os"><?=printPrice($Totalproducidoimpresoraunidades*$data[$x]["item_sellprice_netto"],0)?></td>
               <!-- 105  -->     <td class="content_row_os"><nobr><?=$comentario_prod?></nobr></td>
            </tr>
         <?php

         /* 01*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaIngresoCC"]                             = $data[$x]["fecha_hora_ingreso_cc"];
         /* 02*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaProduccion"]                            = $data[$x]["wok_crtdat"];
         /* 03*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NúmerodeCC"]                                 = $data[$x]["numero_cc"];
         /* 04*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CantidaddeBolsas"]                           = printPrice($data[$x]["cantidad_bolsas"]);
         /* 05*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NumerodeOT"]                                 = $data[$x]["numeroot"];
         /* 06*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Cliente"]                                    = $data[$x]["cliente"];
         /* 07*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TipoBolsa"]                                  = $tipo_bolsa["add_name"];
         /* 08*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FormatoBolsa"]                               = $data[$x]["formato"];
         /* 09*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CodigoProducto"]                             = $data[$x]["codigo_producto"];
         /* 10*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["DescripcionProducto"]                        = $data[$x]["descripcion_producto"];
         /* 11*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CortedeBolsa"]                               = printPrice($corte_m2,4);
         /* 12*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["UM"]                                         = "Mts";
         /* 13*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["AnchodeBolsa"]                               = printPrice($data[$x]["ancho_bolsa"]);
         /* 14*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["AltoFrentedeBolsa"]                          = printPrice($data[$x]["alto_frente_bolsa"]);
         /* 15*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["AltoDorsodeBolsa"]                           = printPrice($data[$x]["alto_dorso_bolsa"]);
         /* 16*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MedidaDoblezSuperior"]                       = printPrice($data[$x]["medida_doblez_superior"]);
         /* 17*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MedidaFuelledeBolsa"]                        = printPrice($data[$x]["medida_fuelle_bolsa"]);
         /* 18*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["LargoManilla"]                               = printPrice($data[$x]["largo manilla"]);
         /* 19*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ColorTela"]                                  = $fabric_color["add_name"];
         /* 20*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CódigoColorTela"]                            = $fabric_color["add_name_eng"];
         /* 21*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Gramaje"]                                    = printPrice($thispos["fab_mat_gramms"]);
         /* 22*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["AnchoBobina"]                                = printPrice($data[$x]["ancho_bobina"]);
         /* 23*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CódigodeTela"]                               = $data[$x]["Codigo_Tela"];
         /* 24*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CantidaddeBobinas"]                          = printPrice($data[$x]["cantidad_bobina"]);
         /* 25*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["PiedeImprenta"]                              = $data[$x]["pie_imprenta"];
         /* 26*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CódgodeBarra"]                               = $data[$x]["codigo_barra"];
         /* 27*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NúmeroCódigodeBarra"]                        = $data[$x]["numero_codigo_barra"];
         /* 28*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Alarma"]                                     = $data[$x]["tien_dispositivo"];
         /* 29*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NúmerodeAlarma"]                             = $data[$x]["dispositivo"];
         /* 30*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NombreMáquina"]                              = $data[$x]["nombre_maquina"];
         /* 31*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NumerodeMáquina"]                            = $data[$x]["numero_maquina"];
         /* 32*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NombreSupervisor"]                           = $supervisores["supervisor"];
         /* 33*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Rutsupervisor"]                              = $supervisores["rutsupervisor"];
         /* 34*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaHoraInicioAlistamiento"]                = $apertura_ini;
         /* 35*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaHoraTérminoAlistamiento"]               = $apertura_fin;
         /* 36*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["EstadodeAlistamiento"]                       = $estado_apert;
         /* 37*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalHorasAlistamiento"]                     = $apertura_dif;
         /* 38*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaHoraInicioProducción"]                  = $prod_ini;
         /* 39*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaHoraTérminoProducción"]                 = $prod_fin;
         /* 40*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["EstadodeProducción"]                         = $estado_prod;
         /* 41*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalHoraProducción"]                        = $prod_dif;
         /* 42*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Turnomañanatardenoche"]                      = $jornadas["type_name_short"];
         /* 43*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["HorasTurno"]                                 = $jornadas["type_name"];
         /* 44*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NombreOperador"]                             = $operador["user_lastname"];
         /* 45*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["RutOperador"]                                = $operador["user_rut"];
         /* 46*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NombreAyudante"]                             = $ayudante["wrk_firstname"].''.$ayudante["wrk_lastname"];
         /* 47*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["RutAyudante"]                                = $ayudante["wrk_rut"];
         /* 48*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["%MermaProgramado"]                           = printPrice($data[$x]["porcentaje_merma"],2);
         /* 49*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ImpresionesalDesarrolloProgramado"]          = (int)$agenda["req_solic_devprints_cc"];
         /* 50*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Totalprogramadoimpresoraunidades"]           = printPrice($Totalprogramadoimpresoraunidades);
         /* 51*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalProgramadoImpresoraPasadasturno"]       = printPrice($mlin_maquina);
         /* 52*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalProgramadoImpresoraMetroslineales"]     = printPrice($Totalprogramadoimpresoraunidades*$corte_m2);
         /* 53*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalProgramadoImpresoraKgs"]                = printPrice($Totalprogramadoimpresoraunidades*$peso_unitario,2);
         /* 54*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ProduccionEsperadasUnidades"]                = "Sin Información";
         /* 55*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CantidadColoresaImprimir"]                   = $k_color;
         /* 56*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ColorImpreso"]                               = $color1;
         /* 57*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalImpresaTerminadasUn"]                   = $k_x_color;
         /* 58*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ImpresionesdelDesarrolloProducido"]          = $data[$x]["req_operador_bastidoramt"];
         /* 59*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Totalproducidoimpresoraunidades"]            = printPrice($Totalproducidoimpresoraunidades,0);
         /* 60*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalProducidoImpresoraPasadasturno"]        = printPrice($Totalproducidoimpresoraunidades/$data[$x]["req_operador_bastidoramt"],0);
         /* 61*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalProducidoImpresoraMetrosLineales"]      = printPrice($produccion_evt_amount_metros_lineales,0);
         /* 62*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalProducidoImpresoraKgs"]                 = printPrice($total_producido,0);
         /* 63*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["EficienciaProductiva%"]                      = "Sin Información";
         /* 64*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["VelocidadMáquinaMetrosxMinuto"]              = printPrice($velocidad_maquina,0);
         /* 65*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["PesoUnitarioKgs"]                            = printPrice($peso_unitario,4);
         /* 66*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["N°BastidorUtilizado"]                        = "Sin Información";
         /* 67*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ImpresionesAlAncho"]                         = $data[$x]["req_solic_devprints_poltype"];
         /* 68*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["AnchoBobina"]                                = printPrice($ancho_bobina,4);
         /* 69*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ColorBobina"]                                = $color_bobina[0]["add_name"];
         /* 70*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CodigoColorBobina"]                          = $color_bobina[0]["add_name_eng"]; 
         /* 71*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["GramajeBobina"]                              = printPrice($color_bobina[0]["item_reg_gsm"],0);
         /* 72*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["EstadoBobibaNuevaUsada"]                     = "Sin Información";
         /* 73*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["N°BobinasProcesadasUnidades"]                = $xx;
         /* 74*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["KilosBobinasProcesadasKgs"]                  = $posicion;
         /* 75*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MetrosLinealesBobinasProcesadasMtsLineales"] = printPrice($mtslineabobinaprocesada,0);
         /* 76*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MermaTotalKilos"]                            = printPrice($total_merma,2);
         /* 77*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Merma%"]                                     = printPrice($porce_merma,2);
         
         /* 78*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Color1Frente"]                               = $colorfron[1];
         /* 79*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Color2Frente"]                               = $colorfron[2];
         /* 80*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Color3Frente"]                               = $colorfron[3];
         /* 81*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Color4Frente"]                               = $colorfron[4];
         /* 82*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Color1Dorso"]                                = $colorback[1];
         /* 83*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Color2Dorso"]                                = $colorback[2];
         /* 84*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Color3Dorso"]                                = $colorback[3];
         /* 85*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Color4Dorso"]                                = $colorback[4];
         /* 86*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["KgsTintaUtilizadas1"]                        = "Sin Información";
         /* 87*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["KgsTintaUtilizadas2"]                        = "Sin Información";
         /* 88*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["KgsTintaUtilizadas3"]                        = "Sin Información";
         /* 89*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["KgsTintaUtilizadas4"]                        = "Sin Información";
         /* 90*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BobinaSobranteKgs"]                          = printPrice($produccion_prod_bobina_kg,2);
         /* 91*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MermaAlistamientoUN"]                        = printPrice($porce_configuracion);
         /* 92*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MermaAlistamientoKgs"]                       = printPrice($merma_configurar,2);
         /* 93*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MermaimpresionUn"]                           = printPrice($porce_impresion);
         /* 94*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MermaImpresionKgs"]                          = printPrice($merma_impresion,2);
         /* 95*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MermaBobinaDefectuosaUn"]                    = printPrice($porce_otros);
         /* 96*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MermaBobinaDefectuosaKgs"]                   = printPrice($merma_otros,2);
         
         /* 97*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TiempoProductivoHoras"]                      = $_RET["_PAUSE_TIME_STR"];
         /* 98*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalParosHoras"]                            = $_RET["_PRODU_TIME_STR"];
         /* 99*/   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TiempoNetoProductivoHoras"]                  = "Sin informacion";
         /* 100*/  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Disponibilidad%"]                            = 0;
         /* 101*/  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Rendimiento%"]                               = 0;
         /* 102*/  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Calidad%"]                                   = 0;
         /* 103*/  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Oee%"]                                       = $data[$x]["item_sellprice_brutto"];
         /* 104 */ $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Producidos"]                                 = printPrice($Totalproducidoimpresoraunidades*$data[$x]["item_sellprice_netto"],0);
         /* 105*/  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ObservaciónProducción"]                      = $comentario_prod;
         
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
      <?php
      //   printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
      ?>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
</form>
<?php
//----------------------------------------------------------------------------------

if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSerigrafia($CON);
  
if($pdffile != "")
{
   $doctitle = "Serigrafia-CC-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Serigrafia-CC-".time().".xls";
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