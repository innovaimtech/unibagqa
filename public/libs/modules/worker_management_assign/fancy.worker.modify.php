<?php
$querystr  = "mid={$_REQUEST["mid"]}&wid={$_REQUEST["wid"]}&wtype=worker";
$querystr .= "&startdate={$_REQUEST["startdate"]}&enddate={$_REQUEST["enddate"]}&plantaid={$_REQUEST["plantaid"]}";

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "assign_turno_type_id_") !== false && strpos($reqkey, "assign_turno_type_id_") == 0)
      {
         $idxpos = substr($reqkey, strrpos($reqkey, "_") +1);

         $assign_turno_type_id   = (int)$_REQUEST["assign_turno_type_id_{$idxpos}"];
         $ass_init_hour          = (int)$_REQUEST["ass_init_hour_{$idxpos}"];
         $ass_init_min           = (int)$_REQUEST["ass_init_min_{$idxpos}"];
         $ass_end_hour           = (int)$_REQUEST["ass_end_hour_{$idxpos}"];
         $ass_end_min            = (int)$_REQUEST["ass_end_min_{$idxpos}"];
         $assign_equipoaid       = (int)$_REQUEST["assign_equipoaid_{$idxpos}"];
         $dayarr                 = explode(".", $_REQUEST["dayidx_{$idxpos}"]);
         $assign_stamp           = (int)$_REQUEST["daystamp_{$idxpos}"];
         $existingid             = (int)$_REQUEST["existingid_{$idxpos}"];
         $ass_comments           = trim(addslashes($_REQUEST["comments_{$idxpos}"]));
         
         if($assign_turno_type_id && $assign_equipoaid)
         {
            if($existingid)
            {
               $sql = " update turnos_config_assign
                        set
                        assign_turno_type_id = {$assign_turno_type_id},
                        assign_equipoaid     = {$assign_equipoaid},
                        ass_init_hour        = {$ass_init_hour},
                        ass_init_min         = {$ass_init_min},
                        ass_end_hour         = {$ass_end_hour},
                        ass_end_min          = {$ass_end_min},
                        ass_comments         = '{$ass_comments}'
                        where
                        id = {$existingid}";
               $CON->no_result($sql);
            }
            else
            {
               $sql = " insert into turnos_config_assign
                        (assign_planta_id, assign_day, assign_month, assign_year, assign_stamp,
                        assign_turno_id, assign_turno_type_id, assign_worker_id, assign_worker_type,
                        assign_equipoaid, ass_init_hour, ass_init_min, ass_end_hour, ass_end_min,
                        ass_comments)
                        VALUES
                        ({$_REQUEST["plantaid"]}, {$dayarr[0]}, {$dayarr[1]}, {$dayarr[2]}, {$assign_stamp},
                         6, {$assign_turno_type_id}, {$_REQUEST["wid"]}, 'worker',
                         {$assign_equipoaid}, {$ass_init_hour}, {$ass_init_min}, {$ass_end_hour},
                         {$ass_end_min}, '{$ass_comments}')";
               $CON->no_result($sql);
            }
         }
         else
         {
            if($existingid)
            {
               $sql = " delete from turnos_config_assign
                        where
                        id = {$existingid}";
               $CON->no_result($sql);
            }
         }
      }
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "clone")
{
   $stamp               = $_REQUEST["startdate"] - (3 * 86400);
   $dayofweek           = date("N", $stamp);
   $daydsc              = 7 - $dayofweek;
   $daydsc              = 6 - $daydsc;

   $startstamp          = $stamp - ($daydsc * 86400);
   $_WEEK_START_STAMP   = mktime(0, 0, 0, date("m", $startstamp), date("d", $startstamp), date("Y", $startstamp));

   $end_stamp           = mktime(15, 0, 0, date("m", $startstamp), date("d", $startstamp), date("Y", $startstamp));
   $end_stamp           += (6 * 86400);
   $_WEEKS_END_STAMP    = mktime(23, 59, 59, date("m", $end_stamp), date("d", $end_stamp), date("Y", $end_stamp));

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from turnos_config_assign t1
            where
            t1.assign_planta_id  = {$_REQUEST["plantaid"]} and
            t1.assign_worker_id  = {$_REQUEST["wid"]} and
            t1.assign_stamp      between {$_WEEK_START_STAMP} and {$_WEEKS_END_STAMP}
            order by t1.ass_init_hour, t1.ass_init_min, t1.ass_end_hour, t1.ass_end_min";
   $oldassigns = $CON->select($sql);
   foreach($oldassigns AS $oldassign)
   {
      $assign_stamp  = $oldassign["assign_stamp"] + (7 * 86400);
      $assign_day    = (int)date("d", $assign_stamp);
      $assign_month  = (int)date("m", $assign_stamp);
      $assign_year   = (int)date("Y", $assign_stamp);

      $sql = " insert into turnos_config_assign
               (assign_planta_id, assign_day, assign_month, assign_year, assign_stamp,
               assign_turno_id, assign_turno_type_id, assign_worker_id, assign_worker_type,
               assign_equipoaid, ass_init_hour, ass_init_min, ass_end_hour, ass_end_min)
               VALUES
               ({$oldassign["assign_planta_id"]}, {$assign_day}, {$assign_month}, {$assign_year}, {$assign_stamp},
                6, {$oldassign["assign_turno_type_id"]}, {$_REQUEST["wid"]}, 'worker',
                {$oldassign["assign_equipoaid"]}, {$oldassign["ass_init_hour"]}, {$oldassign["ass_init_min"]},
                {$oldassign["ass_end_hour"]}, {$oldassign["ass_end_min"]})";
      $CON->no_result($sql);
   }
}
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "delete")
{
   $sql = " delete from turnos_config_assign
            where
            assign_planta_id  = {$_REQUEST["plantaid"]} and
            assign_worker_id  = {$_REQUEST["wid"]} and
            assign_stamp      between {$_REQUEST["startdate"]} and {$_REQUEST["enddate"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
$wdata = getWorkerData($CON, $_REQUEST["wid"], "worker");
for($x = $_REQUEST["startdate"]+3600; $x <= $_REQUEST["enddate"]; $x += 86400)
{
   $dayidx = date("d.m.Y", $x);
   $_WEEK_DAYS[$dayidx] = 1;
}

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.equipo_name
         from equipo t1
         where
         t1.equipo_status > 0 and
         t1.equipo_planta_id = {$_REQUEST["plantaid"]}
         order by 2";
$equipos = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from turnos_types
         where
         type_status > 0 and
         type_libre_act = 0
         order by type_name";
$ttypes = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from turnos_config_assign t1
         where
         t1.assign_planta_id  = {$_REQUEST["plantaid"]} and
         t1.assign_worker_id  = {$_REQUEST["wid"]} and
         t1.assign_stamp      between {$_REQUEST["startdate"]} and {$_REQUEST["enddate"]}
         order by t1.ass_init_hour, t1.ass_init_min, t1.ass_end_hour, t1.ass_end_min";
$assigns = $CON->select($sql);
foreach($assigns AS $assign)
{
   $idx = date("d.m.Y", $assign["assign_stamp"]);
   $_DATA[$idx][] = $assign;
}
?>
<div style="height:2px"></div>
<form action="iframe.fancy.php" method="post" name="xform_data" style="padding:0px;margin:0px;">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="module" value="<?=$_REQUEST["module"]?>">
<input type="hidden" name="wid" value="<?=$_REQUEST["wid"]?>">
<input type="hidden" name="startdate" value="<?=$_REQUEST["startdate"]?>">
<input type="hidden" name="enddate" value="<?=$_REQUEST["enddate"]?>">
<input type="hidden" name="plantaid" value="<?=$_REQUEST["plantaid"]?>">
<input type="hidden" name="exec" value="save">
<?=Nifty_printH("box1", "100%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="125">
   <col width="40%">
   <col width="125">
   <col width="40%">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">
      <span style="float:left;line-height:20px">
         Datos del trabajador
      </span>
      <span style="float:right">
         <?php
         if(!count($_DATA))
         {  ?>
            <input type="button" class="button" value="Copiar de la ultima semana"
            onclick="if(askDel('')) { document.xform_data.exec.value = 'clone';document.xform_data.submit(); } ">
            <?php
         }
         else
         {  ?>
            <input type="button" class="buttonred" value="Eliminar la semana"
            onclick="if(askDel('')) { document.xform_data.exec.value = 'delete';document.xform_data.submit(); } ">
            <?php
         }
         ?>
      </span>
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombres</td>
   <td class="content_row"><?=$wdata["wrk_firstname"]?>&nbsp;</td>
   <td class="content_rowl">Apellidos</td>
   <td class="content_row"><?=$wdata["wrk_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">RUT</td>
   <td class="content_row"><?=$wdata["wrk_rut"]?>&nbsp;</td>
   <td class="content_rowl">Periodo</td>
   <td class="content_row">
      <?=date("d.m.Y", $_REQUEST["startdate"])?> hasta <?=date("d.m.Y", $_REQUEST["enddate"])?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Vista</td>
   <td class="content_row">
      <select class="text" style="width:100%;" name="sql_xmode"
      onchange="gotoModifyView(this.value, '<?=$querystr?>')">
         <option value="0" selected> Vista: Asignación semanal</option>
         <option value="1"> Vista: Incidencias</option>
      </select>
   </td>
   <td class="content_rowl">&nbsp;</td>
   <td class="content_row">&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<script language="JavaScript">
function setAgHours(rowidx, tid)
{
   <?php
   foreach($ttypes AS $ttype)
   {  ?>
      if(tid == '<?=$ttype["id"]?>')
      {
         $('#starthour_' +rowidx).val('<?=sprintf("%02s", $ttype["type_init_hour"])?>');
         $('#startmin_' +rowidx).val('<?=sprintf("%02s", $ttype["type_init_min"])?>');
         $('#endhour_' +rowidx).val('<?=sprintf("%02s", $ttype["type_end_hour"])?>');
         $('#endmin_' +rowidx).val('<?=sprintf("%02s", $ttype["type_end_min"])?>');
      }
      <?php
   }
   ?>
}
</script>
<?=Nifty_printH("box1", "100%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col width="75">
   <col width="190">
   <col width="210">
   <col>
   <col width="220">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Planificación semanal</td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os">Fecha</td>
   <td class="content_tbl_subheader content_row_os">Dia</td>
   <td class="content_tbl_subheader content_row_os">Turno</td>
   <td class="content_tbl_subheader content_row_os" align="center">Horario</td>
   <td class="content_tbl_subheader content_row_os">Máquina</td>
   <td class="content_tbl_subheader content_row_os">Comentarios</td>
</tr>
<?php
$x = 1;
$b = 1;
foreach(array_keys($_WEEK_DAYS) AS $dayidx)
{
   $datearr = explode(".", $dayidx);
   $datestm = mktime(15, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $stamp   = $datestm;
   $datestm = (int)date("N", $datestm);

   $rowcount = 2;
   if(count($_DATA[$dayidx]) > 0)
      $rowcount += count($_DATA[$dayidx]);

   for($xz = 0; $xz < $rowcount; $xz++)
   {
      $row = $_DATA[$dayidx][$xz];

      $dspcss = "";
      if($xz > 0 && !(int)$row["id"])
         $dspcss = "display:none";

      $daycls = str_replace(".", "_", $dayidx);

      $bgrowcss = "";
      if((int)$row["id"])
         $bgrowcss = "background-color:#D1FFD6;";
      ?>
      <tr bgcolor="<?=getRowColor($b)?>" style="<?=$dspcss?>" class="clstr_<?=$daycls?>">
         <td class="content_row_os" style="<?=$bgrowcss?>">
            <?php
            if($xz == 0)
            {  ?>
               <input type="button" class="button" value="+" style="width:20px"
               onclick="$('.clstr_<?=$daycls?>').last().fadeIn(300)">
               <?php
            }
            else
            {  ?>
               <img src="/images/menu/icons/arrow-turn-000-left.png" style="padding-right:4px">
               <?php
            }  
            ?>
            <?=$dayidx?>
         </td>
         <td class="content_row_os" style="<?=$bgrowcss?>"><?=$_LANG["MODULE"]["CAL"][(11 + $datestm)]?></td>
         <td class="content_row_os" style="<?=$bgrowcss?>">
            <input type="hidden" name="dayidx_<?=$x?>" value="<?=$dayidx?>">
            <input type="hidden" name="daystamp_<?=$x?>" value="<?=$stamp?>">
            <input type="hidden" name="existingid_<?=$x?>" value="<?=$row["id"]?>">
            
            <select class="text" style="width:190px" name="assign_turno_type_id_<?=$x?>"
            onchange="setAgHours('<?=$x?>', this.value)">
               <option value="">Seleccione un turno</option>
               <?php
               foreach($ttypes AS $ttype)
               {  ?>
                  <option value="<?=$ttype["id"]?>" <?if($ttype["id"] == $row["assign_turno_type_id"]) echo "selected"?>>
                     <?=$ttype["type_name"]?>
                     (<?=sprintf("%02s", $ttype["type_init_hour"])?>:<?=sprintf("%02s", $ttype["type_init_min"])?>-<?=sprintf("%02s", $ttype["type_end_hour"])?>:<?=sprintf("%02s", $ttype["type_end_min"])?>)
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_row_os" align="center" style="<?=$bgrowcss?>">
            <nobr>
            <input type="text" class="text" style="width:40px;text-align:center"
            name="ass_init_hour_<?=$x?>" id="starthour_<?=$x?>"
            value="<?if((int)$row["id"]) echo sprintf("%02s", $row["ass_init_hour"])?>">
            :
            <input type="text" class="text" style="width:40px;text-align:center"
            name="ass_init_min_<?=$x?>" id="startmin_<?=$x?>"
            value="<?if((int)$row["id"]) echo sprintf("%02s", $row["ass_init_min"])?>">
            -
            <input type="text" class="text" style="width:40px;text-align:center"
            name="ass_end_hour_<?=$x?>" id="endhour_<?=$x?>"
            value="<?if((int)$row["id"]) echo sprintf("%02s", $row["ass_end_hour"])?>">
            :
            <input type="text" class="text" style="width:40px;text-align:center"
            name="ass_end_min_<?=$x?>" id="endmin_<?=$x?>"
            value="<?if((int)$row["id"]) echo sprintf("%02s", $row["ass_end_min"])?>">
            </nobr>
         </td>
         <td class="content_row_os" style="<?=$bgrowcss?>">
            <select class="text" style="width:100%" name="assign_equipoaid_<?=$x?>">
               <option value="">Seleccione una máquina</option>
               <?php
               foreach($equipos AS $equipo)
               {  ?>
                  <option value="<?=$equipo["id"]?>" <?if($equipo["id"] == $row["assign_equipoaid"]) echo "selected"?>><?=$equipo["equipo_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_row_os" style="<?=$bgrowcss?>">
            <input type="text" class="text" style="width:100%;text-align:left"
            name="comments_<?=$x?>" id="comments_<?=$x?>"
            value="<?if((int)$row["id"]) echo $row["ass_comments"]?>">
         </td>
      </tr>
      <?php
      $x++;
   }
   $b++;
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "99%")?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td align="center">
      <?php
      printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "document.xform_data.submit(); ", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
</form>