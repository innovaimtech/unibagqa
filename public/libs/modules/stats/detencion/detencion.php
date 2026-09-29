<?php
//----------------------------------------------------------------------------------
$_sesmodulename         = "detencipon";
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
   $_SESSION[$_sesmodulename]["sql_plantaid"]      = (int)$_REQUEST["sql_plantaid"];
   $_SESSION[$_sesmodulename]["sql_equipotypeid"]  = (int)$_REQUEST["sql_equipotypeid"];
   $_SESSION[$_sesmodulename]["sql_equipoid"]      = (int)$_REQUEST["sql_equipoid"];

   $_SESSION[$_sesmodulename]["sql_tipopausa"]     = (int)$_REQUEST["sql_tipopausa"];
  
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
$sql = "select et.type_ant_title as familia,
                'Sin Informacion' as subfamilia,
                t3.win_equipoid as codigo_maquina,
                e.equipo_name as maquina,
                'Sin Informacion'  as almacen,
                pwoe.evt_pause_id as codigo_parada,
                pause_name as motivo_parada,
                pwoe.evt_comments as comentario_detencion,
                ifnull(descripcion,'') as clasificacion_detencion,
                pwoe.evt_crtdat as fecha_inicio,
                pwoe.evt_enddat as fecha_termino,
                0 as total_detencion,
                pwot.wok_ag_id,
                pa.ag_plantaid,
                concat(user.user_firstname,' ',user.user_lastname) usuario
            from prod_worker_ot_events as pwoe 
            inner join prod_pause_types ppt on ppt.id = pwoe.evt_pause_id
            inner join prod_worker_ot as pwot ON pwot.id = pwoe.evt_prod_worker_otid
            inner join prod_worker_init t3 ON pwot.wok_init_id = t3.id 
            left outer join parametros on tabla = 'CLASIFICA' and codigo = pause_clasifica
            inner join equipo e on e.id = t3.win_equipoid
            inner join equipo_type et on et.id = e.equipo_type_id
            inner join prod_agenda pa on pa.id = pwot.wok_ag_id
            inner join user on user.id = pa.ag_crtusr
        where pwoe.evt_prod_worker_otid 
            and evt_type = 'pause' 
            and evt_pause_id != '1' 
            and pwoe.evt_crtdat between {$sql_datefrom} and {$sql_dateto} 
            and pa.ag_plantaid  = {$_SESSION[$_sesmodulename]["sql_plantaid"]} 
            and pwoe.evt_status > 0
        order by 1";

/*
$sql = " select t1.*, t9.req_number, t12.cust_name, t10.item_amount,
                t11.item_number_prod, t11.item_title, t4.prd_number,
                t7.equipo_name, t4.id 'prod_header_id', t10.fab_design_name
         from prod_worker_ot t1
         INNER JOIN prod_agenda t2        ON t1.wok_ag_id = t2.id
         INNER JOIN prod_worker_init t3   ON t1.wok_init_id = t3.id
         INNER JOIN prod_header t4        ON t2.ag_prdid = t4.id
         LEFT OUTER JOIN equipo t7        ON t3.win_equipoid = t7.id
         LEFT OUTER JOIN equipo_type t8   ON t7.equipo_type_id = t8.id
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
*/
if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"])
   $sql .= " and t7.equipo_type_id = {$_SESSION[$_sesmodulename]["sql_equipotypeid"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_equipoid"])
   $sql .= " and t3.win_equipoid = {$_SESSION[$_sesmodulename]["sql_equipoid"]} ";

$data = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Informe de Detenciones</b></td>
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
      <?=Nifty_printH("box2", "980",0)?>
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
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
</table>
<br>
<table border="0" cellpadding="3" cellspacing="0" width: "100%">
<tr>
   <td>
      <?=Nifty_printH("box1", "100%")?>
        <table style="table-layout: fixed; width: 100%;">
        <colgroup>
                <col>
                <col>
                <col>
                <col>
                <col>
                <col>
                <col>
                <col>
                <col "width:300">
                <col>
                <col>
                <col>
                <col>
                <col>
                <col>
        </colgroup>
        <tr>
            <td colspan="15" class="content_row_os" align="center">Informacion de Detenciones</td>
        </tr>
        <tr>
            <td class="content_rowl content_row_os" align="center">1</td>
            <td class="content_rowl content_row_os" align="center">2</td>
            <td class="content_rowl content_row_os" align="center">3</td>
            <td class="content_rowl content_row_os" align="center">4</td>
            <td class="content_rowl content_row_os" align="center">5</td>
            <td class="content_rowl content_row_os" align="center">6</td>
            <td class="content_rowl content_row_os" align="center">7</td>
            <td class="content_rowl content_row_os" align="center">8</td>
            <td class="content_rowl content_row_os" align="center">9</td>
            <td class="content_rowl content_row_os" align="center">10</td>
            <td class="content_rowl content_row_os" align="center">11</td>
            <td class="content_rowl content_row_os" align="center">12</td>
            <td class="content_rowl content_row_os" align="center">13</td>
            <td class="content_rowl content_row_os" align="center">14</td>
            <td class="content_rowl content_row_os" align="center">15</td>
        </tr>
        <tr>
            <!-- 1 --> <td rowspan="2" class="content_rowl content_row_os" align="center">Usuario</td>
            <!-- 2  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Familia</td>
            <!-- 3  --> <td rowspan="2" class="content_rowl content_row_os" align="center">SubFamilia</td>
            <!-- 4  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Código Máquina</td>
            <!-- 5  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Nombre Maquina</td>
            <!-- 6  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Almacen</td>
            <!-- 7  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Codigo Parada</td>
            <!-- 8  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Motivo Detención</td>
            <!-- 9  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Comentarios</td>
            <!-- 10  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Clasificación Detención</td>
            <!-- 11  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Inicio Detención</td>
            <!-- 12  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Hora Inicio Detención</td>
            <!-- 13 --> <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Fin Detención</td>
            <!-- 14 --> <td rowspan="2" class="content_rowl content_row_os" align="center">Hora Fin Detención</td>
            <!-- 15 --> <td rowspan="2" class="content_rowl content_row_os" align="center">Total Tiempo Colacion</td>
        </tr>
        <tr>
        </tr>
        <?php
        for($x = 0; $x < count($data) && $data != false; $x++)
        {
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                <td class="content_row_os"><?=$data[$x]["usuario"]?></td>
                <td class="content_row_os"><?=$data[$x]["familia"]?></td>
                <td class="content_row_os"><nobr><?=$data[$x]["subfamilia"]?></nobr></td>
                <td class="content_row_os"><nobr><?=$data[$x]["codigo_maquina"]?></nobr></td>
                <td class="content_row_os"><nobr><?=$data[$x]["maquina"]?></nobr></td>
                <td class="content_row_os"><nobr><?=$data[$x]["almacen"]?></nobr></td>
                <td class="content_row_os"><nobr><?=$data[$x]["codigo_parada"]?></nobr></td>
                <td class="content_row_os"><?=$data[$x]["motivo_parada"]?></td>
                <td class="content_row_os" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                   <?=$data[$x]["comentario_detencion"]?></td>
                <td class="content_row_os"><nobr><?=$data[$x]["clasificacion_detencion"]?></nobr></td>
                <td class="content_row_os"><nobr><?=date('d/m/Y',$data[$x]["fecha_inicio"])?></nobr></td>
                <td class="content_row_os"><nobr><?=date('H:i',$data[$x]["fecha_inicio"])?></nobr></td>
                <td class="content_row_os"><nobr><?=date('d/m/Y',$data[$x]["fecha_termino"])?></nobr></td>
                <td class="content_row_os"><nobr><?=date('H:i',$data[$x]["fecha_termino"])?></nobr></td>
                <?php
                $inicio   = $data[$x]["fecha_inicio"];   
                $termino = $data[$x]["fecha_termino"]; 
                $diferencia_segundos = $termino - $inicio;
                $horas   = floor($diferencia_segundos / 3600);
                $minutos = floor(($diferencia_segundos % 3600) / 60);
                $hhmm = str_pad($horas, 2, "0", STR_PAD_LEFT) . ":" . str_pad($minutos, 2, "0", STR_PAD_LEFT);
                ?>
                <td class="content_row_os"><?=$hhmm?></td>
            </tr>
            <?php

            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["familia"]                 = $data[$x]["familia"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["subfamilia"]              = $data[$x]["subfamilia"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["codigo_maquina"]          = $data[$x]["codigo_maquina"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["maquina"]                 = $data[$x]["maquina"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["almacen"]                 = $data[$x]["almacen"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["codigo_parada"]           = $data[$x]["codigo_parada"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["motivo_parada"]           = $data[$x]["motivo_parada"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["comentario_detencion"]    = $data[$x]["comentario_detencion"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["clasificacion_detencion"] = $data[$x]["clasificacion_detencion"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fecha_inicio"]            = $data[$x]["fecha_inicio"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fecha_termino"]           = $data[$x]["fecha_termino"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["hhmm"]                    = $hhmm;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["usuario"]                 = $data[$x]["usuario"];
            
        }
        if(!$x)
        { 
            ?>
            <tr bgcolor="<?=getRowColor(0)?>">
                <td class="content_row" colspan="15" align="center">
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
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsDetenciones($CON);
?>
<?php
if($xlsfile != "")
{
   $doctitle = "Detenciones-".time().".xls";
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