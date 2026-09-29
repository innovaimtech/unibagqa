<?php
//----------------------------------------------------------------------------------
$_BASTIDOR_TABLE = 645;

$sql = " select codigo
         from parametros
         where
         id = {$_BASTIDOR_TABLE}";
$_BASTIDOR_CODE = $CON->select($sql);
$_BASTIDOR_CODE = trim(addslashes($_BASTIDOR_CODE[0]["codigo"]));

$sql = " select *
         from parametros
         where
         tabla = '{$_BASTIDOR_CODE}'
         order by codigo";
$_BASTIDOR_PRMS = $CON->select($sql);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["pulposave"] && $_SESSION["_PROD_setcolormode"] != "")
{
   $_REQUEST["pulpo_temp_horno_frente"]         = (int)trim($_REQUEST["pulpo_temp_horno_frente"]);
   $_REQUEST["pulpo_temp_horno_dorso"]          = (int)trim($_REQUEST["pulpo_temp_horno_dorso"]);
   $_REQUEST["pulpo_maq_speed_frente"]          = (float)getPrice(trim($_REQUEST["pulpo_maq_speed_frente"]),2);
   $_REQUEST["pulpo_maq_speed_dorso"]           = (float)getPrice(trim($_REQUEST["pulpo_maq_speed_dorso"]),2);
   $_REQUEST["pulpo_horno_speed_frente"]        = (float)getPrice(trim($_REQUEST["pulpo_horno_speed_frente"]),2);
   $_REQUEST["pulpo_horno_speed_dorso"]         = (float)getPrice(trim($_REQUEST["pulpo_horno_speed_dorso"]),2);
   $_REQUEST["pulpo_medidas_camillas_frente"]   = trim(addslashes($_REQUEST["pulpo_medidas_camillas_frente"]));
   $_REQUEST["pulpo_medidas_camillas_dorso"]    = trim(addslashes($_REQUEST["pulpo_medidas_camillas_dorso"]));

   $sql = " update prod_agenda
            set
            pulpo_temp_horno_frente          = {$_REQUEST["pulpo_temp_horno_frente"]},
            pulpo_temp_horno_dorso           = {$_REQUEST["pulpo_temp_horno_dorso"]},
            pulpo_maq_speed_frente           = {$_REQUEST["pulpo_maq_speed_frente"]},
            pulpo_maq_speed_dorso            = {$_REQUEST["pulpo_maq_speed_dorso"]},
            pulpo_horno_speed_frente         = {$_REQUEST["pulpo_horno_speed_frente"]},
            pulpo_horno_speed_dorso          = {$_REQUEST["pulpo_horno_speed_dorso"]},
            pulpo_medidas_camillas_frente    = '{$_REQUEST["pulpo_medidas_camillas_frente"]}',
            pulpo_medidas_camillas_dorso     = '{$_REQUEST["pulpo_medidas_camillas_dorso"]}'
            where
            id = {$_REQUEST["agid"]}";
   $CON->no_result($sql);

   $sql = " delete from prod_agenda_v2_pulpo
            where
            ag_id = {$_REQUEST["agid"]} and
            val_colormode = '{$_SESSION["_PROD_setcolormode"]}'";
   $CON->no_result($sql);

   for($px = 0; $px < 10; $px++)
   {
      $color_id            = trim(addslashes($_REQUEST["color_id_{$px}"]));
      $val_temp_secador    = (int)trim($_REQUEST["val_temp_secador_{$px}"]);
      $val_num_bastidor    = trim(addslashes($_REQUEST["val_num_bastidor_{$px}"]));
      $val_mesh            = (float)getPrice(trim($_REQUEST["val_mesh_{$px}"]),6);
      $val_total_tinta     = (float)getPrice(trim($_REQUEST["val_total_tinta_{$px}"]),2);
      $val_pos             = (int)trim($_REQUEST["val_pos_{$px}"]);
      $val_colormode       = $_SESSION["_PROD_setcolormode"];

      $val_carga_tinta     = (float)getPrice(trim($_REQUEST["val_carga_tinta_{$px}"]),2);
      $val_sobrante_tinta  = (float)getPrice(trim($_REQUEST["val_sobrante_tinta_{$px}"]),2);
      $val_total_tinta    += $val_carga_tinta;
      $val_total_tinta    -= $val_sobrante_tinta;

      if(strpos($val_num_bastidor, "@@@@@@") !== false)
      {
         $val_num_bastidor = explode("@@@@@@", $val_num_bastidor);
         $val_num_bastidor = $val_num_bastidor[0];
      }


      if($color_id == "-1")
      {
         $val_num_bastidor = "";
         $val_mesh         = 0;
         $val_total_tinta  = 0;
      }
      elseif($color_id != "")
      {
         $val_temp_secador = 0;
      }

      if($color_id != "")
      {
         $sql = " insert into prod_agenda_v2_pulpo
                  (ag_id, color_id, val_temp_secador, val_num_bastidor, val_mesh,
                   val_total_tinta, val_pos, val_colormode)
                  VALUES
                  ({$_REQUEST["agid"]}, '{$color_id}', {$val_temp_secador}, '{$val_num_bastidor}',
                  {$val_mesh}, {$val_total_tinta}, {$val_pos}, '{$val_colormode}')";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from prod_agenda
         where
         id = {$_REQUEST["agid"]}";
$pdata = $CON->select($sql);
$pdata = $pdata[0];

//----------------------------------------------------------------------------------
$pidx  = strtolower($_SESSION["_PROD_setcolormode"]);
$pmode = "front";
if($pidx == "dorso")
   $pmode = "back";

//----------------------------------------------------------------------------------
$sql = " select *
         from prod_agenda_v2_pulpo
         where
         ag_id          = {$_REQUEST["agid"]} and
         val_colormode  = '{$_SESSION["_PROD_setcolormode"]}'
         order by val_pos";
$xposdata = $CON->select($sql);
$posdata = Array();
foreach($xposdata AS $xposdatarow)
   $posdata[$xposdatarow["val_pos"]] = $xposdatarow;
?>
<form action="prodwrk.php#idx_v2pulpo_tr" method="post" name="xform_v2pulpo">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<input type="hidden" name="setcolormode" value="<?=$_SESSION["_PROD_setcolormode"]?>">
<input type="hidden" name="pulposave" value="1">
<?php
// echo "<pre>";
// print_r($_REQUEST);
?>
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="mode" value="<?=$_REQUEST["mode"]?>">
<input type="hidden" name="overridemode" value="<?=$_REQUEST["overridemode"]?>">
<input type="hidden" name="fromautocontrol" value="<?=$_REQUEST["fromautocontrol"]?>">
<input type="hidden" name="setnew" value="<?=$_REQUEST["setnew"]?>">
<input type="hidden" name="sql_catid" value="<?=$_REQUEST["sql_catid"]?>">
<table border="0" width="100%" cellpadding="6" cellspacing="0">
<colgroup>
   <col width="250">
   <col>
   <col>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="8" align="center">
      <i class="fa fa-fw fa-gear"></i> Información de Fabricación
   </td>
</tr>
<tr >
   <td class="tdleft" style="background-color:#EEEEEE;">Temperatura Horno [°C]</td>
   <td class="tdnrm">
      <input class="inptxt" type="text" style="width:120px"
      value="<?if((float)$pdata["pulpo_temp_horno_{$pidx}"]) echo printPrice($pdata["pulpo_temp_horno_{$pidx}"])?>"
      name="pulpo_temp_horno_<?=$pidx?>">
   </td>
   <td class="tdleft" style="background-color:#EEEEEE;">Velocidad máquina</td>
   <td class="tdnrm">
      <input class="inptxt" type="text" style="width:120px"
      value="<?if((float)$pdata["pulpo_maq_speed_{$pidx}"]) echo printPrice($pdata["pulpo_maq_speed_{$pidx}"],2)?>"
      name="pulpo_maq_speed_<?=$pidx?>">
   </td>
   <td class="tdleft" style="background-color:#EEEEEE;">Lado a imprimir</td>
   <td class="tdnrm"><?=$_SESSION["_PROD_setcolormode"]?></td>
</tr>
<tr>
   <td class="tdleft" style="background-color:#EEEEEE;">Velocidad Horno</td>
   <td class="tdnrm">
      <input class="inptxt" type="text" style="width:120px"
      value="<?if((float)$pdata["pulpo_horno_speed_{$pidx}"]) echo printPrice($pdata["pulpo_horno_speed_{$pidx}"],2)?>"
      name="pulpo_horno_speed_<?=$pidx?>">
   </td>
   <td class="tdleft" style="background-color:#EEEEEE;">Medidas Camillas</td>
   <td class="tdnrm">
      <input class="inptxt" type="text" style="width:120px"
      value="<?=$pdata["pulpo_medidas_camillas_{$pidx}"]?>"
      name="pulpo_medidas_camillas_<?=$pidx?>">
   </td>
   <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
   <td class="tdnrm">&nbsp;</td>
</tr>
</table>
<div style="height:25px"></div>
<table border="0" width="100%" cellpadding="6" cellspacing="0">
<colgroup>
   <col width="90">
   <col width="130">
</colgroup>
<tr>
   <td class="tdleft" style="background-color:#EEEEEE;">Unidad N°</td>
   <td class="tdleft" style="background-color:#EEEEEE;" align="center">Subir / Bajar</td>
   <td class="tdleft" style="background-color:#EEEEEE;">Colores</td>
   <td class="tdleft" style="background-color:#EEEEEE;">Temp. Secador [°C]</td>
   <td class="tdleft" style="background-color:#EEEEEE;">Número de bastidor</td>
   <td class="tdleft" style="background-color:#EEEEEE;">Mesh</td>
   <td class="tdleft" style="background-color:#EEEEEE;">Total tinta</td>
   <td class="tdleft" style="background-color:#EEEEEE;">Carga de tinta [kg]</td>
   <td class="tdleft" style="background-color:#EEEEEE;">Sobrante [kg]</td>
</tr>
<?php
// echo "<pre>";
// print_r($posdata);
for($px = 0; $px < 10; $px++)
{
   $val_temp_secador_css   = "background-color:#DDDDDD";
   $val_temp_secador_rdl   = "readonly";
   $val_num_bastidor_css   = "background-color:#DDDDDD";
   $val_num_bastidor_rdl   = "disabled";
   $val_mesh_css           = "background-color:#DDDDDD";
   $val_mesh_rdl           = "readonly";
   $val_total_tinta_css    = "background-color:#DDDDDD";
   $val_total_tinta_rdl    = "readonly";
   $val_carga_tinta_css    = "background-color:#DDDDDD";
   $val_carga_tinta_rdl    = "readonly";
   $val_sobrante_tinta_css = "background-color:#DDDDDD";
   $val_sobrante_tinta_rdl = "readonly";

   if($posdata[$px]["color_id"] == "-1")
   {
      $val_temp_secador_css   = "";
      $val_temp_secador_rdl   = "";
   }
   elseif($posdata[$px]["color_id"] != "")
   {
      $val_num_bastidor_css   = "background-color:#FFFFFF";
      $val_num_bastidor_rdl   = "";
      $val_mesh_css           = "";
      $val_mesh_rdl           = "";
      $val_total_tinta_css    = "";
      $val_total_tinta_rdl    = "";
      $val_carga_tinta_css    = "";
      $val_carga_tinta_rdl    = "";
      $val_sobrante_tinta_css = "";
      $val_sobrante_tinta_rdl = "";
   }
   ?>
   <tr id="idx_pulpopos_<?=$px?>">
      <td class="tdnrm">Unidad N°<?=($px +1)?></td>
      <td class="tdnrm" align="center">
         <nobr>
         <input type="hidden" name="val_pos_<?=$px?>" id="val_pos_<?=$px?>" value="<?=$px?>">
         <i class="fa fa-fw fa-arrow-circle-up" style="font-size:20px;cursor:pointer"
         onclick="setPulpoPos($(this), 'up')"></i>
         <i class="fa fa-fw fa-arrow-circle-down" style="font-size:20px;cursor:pointer"
         onclick="setPulpoPos($(this), 'down')"></i>
         </nobr>
      </td>
      <td class="tdnrm">
         <select style="width:200px;background-color:#FFFFFF;padding:2px;float:left" class="inptxt"
         name="color_id_<?=$px?>" id="color_id_<?=$px?>"
         onchange="setPulpoPosOpts('<?=$px?>')">
            <option value="">[N/A]</option>
            <option value="-1" <?if($posdata[$px]["color_id"] == "-1") echo "selected"?>>[SECADOR]</option>
            <?php
            for($c = 1; $c <= 10; $c++)
            {
               if((int)$agenda["fab_print_colors_{$pmode}_{$c}"])
               {  ?>
                  <option value="<?=$agenda["fab_print_colordesc_{$c}"]?>"
                  <?if($posdata[$px]["color_id"] == $agenda["fab_print_colordesc_{$c}"]) echo "selected"?>><?=$agenda["fab_print_colordesc_{$c}"]?></option>
                  <?php
               }
            }
            ?>
         </select>
      </td>
      <td class="tdnrm">
         <input class="inptxt" type="text" style="text-align:center;width:120px;<?=$val_temp_secador_css?>" <?=$val_temp_secador_rdl?>
         value="<?if((int)$posdata[$px]["val_temp_secador"]) echo (int)$posdata[$px]["val_temp_secador"]?>"
         name="val_temp_secador_<?=$px?>" id="val_temp_secador_<?=$px?>">
      </td>
      <td class="tdnrm">
         <select class="inptxt" style="text-align:left;width:200px;<?=$val_num_bastidor_css?>" <?=$val_num_bastidor_rdl?>
         name="val_num_bastidor_<?=$px?>" id="val_num_bastidor_<?=$px?>"
         onchange="setBastidorInfo('<?=$px?>', this.value)">
            <option value="">Seleccione</option>
            <?php
            foreach($_BASTIDOR_PRMS AS $_BASTIDOR_PRM)
            {  ?>
               <option value="<?=$_BASTIDOR_PRM["codigo"]?>@@@@@@<?=str_replace(".", ",", $_BASTIDOR_PRM["valor1"])?>" <?if($_BASTIDOR_PRM["codigo"] == $posdata[$px]["val_num_bastidor"]) echo "selected"?>><?=$_BASTIDOR_PRM["codigo"]?>: <?=$_BASTIDOR_PRM["descripcion"]?></option>
               <?php
            }
            ?>
         </select>

         <!--
         <input class="inptxt" type="text" style="text-align:center;width:120px;<?=$val_num_bastidor_css?>" <?=$val_num_bastidor_rdl?>
         value="<?if((int)$posdata[$px]["val_num_bastidor"]) echo (int)$posdata[$px]["val_num_bastidor"]?>"
         name="val_num_bastidor_<?=$px?>" id="val_num_bastidor_<?=$px?>">
         -->
      </td>
      <td class="tdnrm">
         <input class="inptxt" type="text" style="text-align:center;width:120px;<?=$val_mesh_css?>" <?=$val_mesh_rdl?>
         value="<?if((float)$posdata[$px]["val_mesh"]) echo str_replace(".", ",", $posdata[$px]["val_mesh"])?>"
         name="val_mesh_<?=$px?>" id="val_mesh_<?=$px?>">
      </td>
      <td class="tdnrm">
         <input class="inptxt" type="text" style="text-align:center;width:100px;<?=$val_total_tinta_css?>" readonly
         value="<?if((float)$posdata[$px]["val_total_tinta"]) echo printPrice($posdata[$px]["val_total_tinta"],2)?>"
         name="val_total_tinta_<?=$px?>" id="val_total_tinta_<?=$px?>">
      </td>
      <td class="tdnrm">
         <input class="inptxt" type="text" style="text-align:center;width:100px;<?=$val_carga_tinta_css?>" <?=$val_carga_tinta_rdl?>
         value=""
         name="val_carga_tinta_<?=$px?>" id="val_carga_tinta_<?=$px?>">
      </td>
      <td class="tdnrm">
         <input class="inptxt" type="text" style="text-align:center;width:100px;<?=$val_sobrante_tinta_css?>" <?=$val_sobrante_tinta_rdl?>
         value=""
         name="val_sobrante_tinta_<?=$px?>" id="val_sobrante_tinta_<?=$px?>">
      </td>
   </tr>
   <?php
}
?>
</table>
<table border="0" width="100%" cellpadding="6" cellspacing="0">
<tr>
    <td colspan="6" align="right">
      <div class="btngreen" onclick="submitForm(document.xform_v2pulpo)" style="width:200px">
         <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar
      </div>
    </td>
</tr>
</table>
<script language="Javascript">
function setBastidorInfo(xidx, bastval)
{
   $('#val_mesh_' +xidx).val('');
   if(bastval.indexOf('@@@@@@') > -1)
   {
      var bastvalarr = bastval.split('@@@@@@');
      bastvalarr = bastvalarr[1];
      $('#val_mesh_' +xidx).val(bastvalarr);
   }
}
function setPulpoPosOpts(xidx)
{
   var val_temp_secador_css   = "#DDDDDD";
   var val_temp_secador_rdl   = true;
   var val_num_bastidor_css   = "#DDDDDD";
   var val_num_bastidor_rdl   = true;
   var val_mesh_css           = "#DDDDDD";
   var val_mesh_rdl           = true;
   var val_total_tinta_css    = "#DDDDDD";
   var val_total_tinta_rdl    = true;
   var val_carga_tinta_css    = "#DDDDDD";
   var val_carga_tinta_rdl    = true;
   var val_sobrante_tinta_css = "#DDDDDD";
   var val_sobrante_tinta_rdl = true;

   var colorval = $('#color_id_' +xidx).val();

   if(colorval != '')
   {
      if(colorval == '-1')
      {
         val_temp_secador_css = '';
         val_temp_secador_rdl = false;
      }
      else
      {
         val_num_bastidor_css   = "#FFFFFF";
         val_num_bastidor_rdl   = false;
         val_mesh_css           = "";
         val_mesh_rdl           = false;
         val_total_tinta_css    = "";
         val_total_tinta_rdl    = false;
         val_carga_tinta_css    = "";
         val_carga_tinta_rdl    = false;
         val_sobrante_tinta_css = "";
         val_sobrante_tinta_rdl = false;
      }
   }

   $('#val_temp_secador_' +xidx).css({'background-color':val_temp_secador_css}).attr('readonly', val_temp_secador_rdl);
   $('#val_num_bastidor_' +xidx).css({'background-color':val_num_bastidor_css}).attr('disabled', val_num_bastidor_rdl);
   $('#val_mesh_' +xidx).css({'background-color':val_mesh_css}).attr('readonly', val_mesh_rdl);
   $('#val_total_tinta_' +xidx).css({'background-color':val_total_tinta_css}).attr('readonly', true);
   $('#val_carga_tinta_' +xidx).css({'background-color':val_carga_tinta_css}).attr('readonly', val_carga_tinta_rdl);
   $('#val_sobrante_tinta_' +xidx).css({'background-color':val_sobrante_tinta_css}).attr('readonly', val_sobrante_tinta_rdl);
}
function setPulpoPos(jqObj, xdire)
{
   if(xdire == 'up') //&& xpos > 0
   {
      var current_tr_obj   = jqObj.parent().parent().parent();
      var current_tr_id    = current_tr_obj.attr('id').split('_')[2];
      var current_val_obj  = $('#val_pos_' +current_tr_id);

      var other_tr_obj     = jqObj.parent().parent().parent().prev();

      if(other_tr_obj.attr('id') != '')
      {
         var other_tr_id      = other_tr_obj.attr('id').split('_')[2];
         var other_val_obj    = $('#val_pos_' +other_tr_id);

         current_tr_obj.insertBefore(other_tr_obj);

         var curr_xtemp = current_val_obj.val();
         var other_xtemp = other_val_obj.val();
         current_val_obj.val(other_xtemp);
         other_val_obj.val(curr_xtemp);

         var curr_xtemp2 = current_val_obj.val();
         var other_xtemp2 = other_val_obj.val();
         current_tr_obj.find('td:first-child').html('Unidad N°' +(other_xtemp2));
         other_tr_obj.find('td:first-child').html('Unidad N°' +(parseInt(other_xtemp2)+1));
      }
   }
   else if(xdire == 'down') //&& xpos < 9
   {
      var current_tr_obj   = jqObj.parent().parent().parent();
      var current_tr_id    = current_tr_obj.attr('id').split('_')[2];
      var current_val_obj  = $('#val_pos_' +current_tr_id);

      var other_tr_obj     = jqObj.parent().parent().parent().next();

      if(other_tr_obj.attr('id') != '')
      {
         var other_tr_id      = other_tr_obj.attr('id').split('_')[2];
         var other_val_obj    = $('#val_pos_' +other_tr_id);

         current_tr_obj.insertAfter(other_tr_obj);

         var curr_xtemp = current_val_obj.val();
         var other_xtemp = other_val_obj.val();

         current_val_obj.val(other_xtemp);
         other_val_obj.val(curr_xtemp);

         var curr_xtemp2 = current_val_obj.val();
         var other_xtemp2 = other_val_obj.val();
         current_tr_obj.find('td:first-child').html('Unidad N°' +(parseInt(curr_xtemp2)+1));
         other_tr_obj.find('td:first-child').html('Unidad N°' +(curr_xtemp2));
      }
   }
}
</script>
<input type="submit" style="opacity:0.1;border:0px;background-color:transparent;width:1px;height:1px;position:fixed;left:-10p;top:-10px">
</form>
<?php