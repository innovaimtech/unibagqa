<?php
$_REQUEST["agid"] = (int)$_REQUEST["agid"];


if((int)$_REQUEST["refid"])
{
   $sql = " select *
            from prod_worker_ot_events
            where
            id = {$_REQUEST["refid"]}";
   $refevent = $CON->select($sql);
   $refevent = $refevent[0];
}

//----------------------------------------------------------------------------------
$_FORMMODE        = "productionsave";
$_SERICOLORS      = Array();
$_SERICOLORS_ACT  = false;
if($agenda["fab_printtype"] == "SERI" && (int)$hasopeninit["equipo_prod_serimulticolors_act"])
{
   for($x = 1; $x <= 5; $x++)
   {
      if($agenda["fab_print_colordesc_{$x}"] != "")
         $_SERICOLORS[$agenda["fab_print_colordesc_{$x}"]] = 1;
   }
   if(count($_SERICOLORS) > 1)
   {
      $_SERICOLORS_ACT  = true;
      $_FORMMODE        = "productionmulticolorsave";
   }
}

//----------------------------------------------------------------------------------
$_ISSELLADORA = false;
if(strpos(strtoupper($hasopeninit["type_ant_title"]), "SELLADORA") !== false)
   $_ISSELLADORA = true;

$_ISREBOBINADORA = false;
if(strpos(strtoupper($hasopeninit["type_ant_title"]), "REBOBIN") !== false)
   $_ISREBOBINADORA = true;

//----------------------------------------------------------------------------------
$_EMBALAJE_PROD_CALCINP = false;
if((int)$hasopeninit["type_createstock_act"] && (int)$_REQUEST["refid"])
   $_EMBALAJE_PROD_CALCINP = true;

?>
<script language="Javascript">
function prdinpcheck(xform)
{
   if(xform.sql_metrotype.value == '')
      return checkform(new Array(this.prod_amount<?if($_SERICOLORS_ACT) echo ", this.prod_seri_color";?> <?php if(strpos(strtoupper($hasopeninit["type_ant_title"]), "IMPRESORA") !== false) echo ", this.prod_bobina_kg" ?>))
   else if(xform.sql_metrotype.value == 'metros_maquina')
      return checkform(new Array(this.evt_amount_metros_maquina<?php if(strpos(strtoupper($hasopeninit["type_ant_title"]), "IMPRESORA") !== false) echo ", this.prod_bobina_kg" ?>));
   else if(xform.sql_metrotype.value == 'metros_lineales')
      return checkform(new Array(this.evt_amount_metros_lineales<?php if(strpos(strtoupper($hasopeninit["type_ant_title"]), "IMPRESORA") !== false) echo ", this.prod_bobina_kg" ?>));
}
</script>
<form action="prodwrk.php" method="post" name="xform_inp" id="xform_inp"
onsubmit="return prdinpcheck(this)">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="<?=$_FORMMODE?>">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="submode" value="">
<input type="hidden" name="reloadact" value="">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="6" cellspacing="0" style="table-layout:fixed">
      <colgroup>
         <col width="150">
         <col>
         <?php
         if((int)$_REQUEST["refid"] && !(int)$refevent["evt_enddat"])
         {  ?>
            <col width="160">
            <?php
         }
         ?>
      </colgroup>
      <tr>
         <td class="tdleft">Tipo</td>
         <td class="tdnrm" colspan="2"><?=$hasopeninit["type_ant_title"]?></td>
         <?php
         /*
         if((int)$_REQUEST["refid"] && !(int)$refevent["evt_enddat"])
         {  ?>
            <td class="tdnrm" align="right" rowspan="4" valign="top">
               <div class="btngrey" style="width:160px"
               onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.mermas.php?refid=<?=$_REQUEST["refid"]?>', 'iframe', '500', '300', 'auto')">
                  <i class="fa fa-fw fa-trash" style="color:white;"></i> Mermas&nbsp;
               </div>
               <div style="height:5px"></div>
               <div class="btngrey" style="width:160px"
               onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/fancy.repairs.php?refid=<?=$_REQUEST["refid"]?>', 'iframe', '500', '300', 'auto')">
                  <i class="fa fa-fw fa-wrench" style="color:white;"></i> Unidades a reparar&nbsp;
               </div>
            </td>
            <?php
         }
         */
         ?>
      </tr>
      <tr>
         <td class="tdleft">Máquina</td>
         <td class="tdnrm" colspan="2"><?=$hasopeninit["equipo_name"]?></td>
      </tr>
      <?php
      if((int)$hasopeninit["equipo_prod_isprinter_flexo"])
      {  ?>
         <tr style="display:none">
            <td class="tdleft">Producción</td>
            <td class="tdnrm" colspan="2">
               <input type="text" class="inptxt" name="prod_amount" id="prod_amount"
               value="0">
            </td>
         </tr>
         <?php
         if($hasopeninit["equipo_prod_printer_metrotype"] == "mtrs/máquina")
         {  ?>
            <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
               <td class="tdleft">Producción</td>
               <td class="tdnrm" colspan="2">
                  <input type="hidden" name="sql_metrotype" value="metros_maquina">
                  <input type="text" class="inptxt" name="evt_amount_metros_maquina" id="evt_amount_metros_maquina"
                  value="<?php
                     if(!(int)$refevent["id"])
                        echo "0";
                     elseif($refevent["evt_amount_metros_maquina"] > 0.00)
                        echo printPrice($refevent["evt_amount_metros_maquina"]);
                     ?>">
                  mtrs/máquina
               </td>
            </tr>
            <?php
         }
         else
         {  ?>
            <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
               <td class="tdleft">Producción</td>
               <td class="tdnrm" colspan="2">
                  <input type="hidden" name="sql_metrotype" value="metros_lineales">
                  <input type="text" class="inptxt" name="evt_amount_metros_lineales" id="evt_amount_metros_lineales"
                  value="<?php
                     if(!(int)$refevent["id"])
                        echo "0";
                     elseif($refevent["evt_amount_metros_lineales"] > 0.00)
                        echo printPrice($refevent["evt_amount_metros_lineales"]);
                     ?>">
                  mtrs/lineales
               </td>
            </tr>
            <?php

         }
      }
      else
      {
         if((int)$hasopeninit["equipo_prod_isprinter_seri"])
         {  ?>
            <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
               <td class="tdleft">Producción</td>
               <td class="tdnrm" colspan="2">
                  <input type="hidden" name="sql_metrotype" value="">
                  <input type="text" class="inptxt" name="prod_amount" id="prod_amount"
                  style="background-color:#EEEEEE" readonly
                  value="<?php
                     if(!(int)$refevent["id"])
                        echo "0";
                     elseif($refevent["evt_amount"] > 0.00)
                        echo printPrice($refevent["evt_amount"]);
                     ?>">
                  unidades
                  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                  <input type="text" id="idx_seri_pasadas_inp" class="inptxt" style="width:150px"
                  placeholder="Cantidad de pasadas">
                  <input type="button" class="btngreen" value="Calcular unidades" style="padding:5px;border:0px;font-size:12px;margin-left:5px"
                  onclick="calcUnitsFromPasadas()">
               </td>
            </tr>
            <?php

         }
         else
         {  ?>
            <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
               <td class="tdleft">Producción</td>
               <td class="tdnrm" colspan="2">
                  <input type="hidden" name="sql_metrotype" value="">
                  <input type="text" class="inptxt" name="prod_amount" id="prod_amount"
                  style="<?if($_EMBALAJE_PROD_CALCINP) echo "background-color:#EEEEEE"?>"
                  <?if($_EMBALAJE_PROD_CALCINP) echo "readonly"?>
                  value="<?php
                     if(!(int)$refevent["id"])
                        echo "0";
                     elseif($refevent["evt_amount"] > 0.00)
                        echo printPrice($refevent["evt_amount"]);
                     ?>">
                     unidades
               </td>
            </tr>
            <?php
         }
      }
      if($_SERICOLORS_ACT)
      {  ?>
         <tr style="">
            <td class="tdleft">Color</td>
            <td class="tdnrm" colspan="2">
               <select class="inptxt" name="prod_seri_color" id="prod_seri_color" style="background-color:#FFFFFF;width:350px">
                  <option value="">Seleccione un color de impresión</option>
                  <?php
                  foreach(array_keys($_SERICOLORS) AS $_SERICOLORIDX)
                  {  ?>
                     <option value="<?=$_SERICOLORIDX?>" <?php if($refevent["prod_seri_color"] == $_SERICOLORIDX) echo "selected"?>>
                        <?=$_SERICOLORIDX?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <?php
      }
      if(strpos(strtoupper($hasopeninit["type_ant_title"]), "IMPRESORA") !== false)
      {  ?>
         <tr>
            <td class="tdleft">Peso Bobina</td>
            <td class="tdnrm" colspan="2">
               <input type="text" class="inptxt" name="prod_bobina_kg" id="prod_bobina_kg"
               style="width:100px" value="<?if((int)$refevent["prod_bobina_kg"]) echo printPrice($refevent["prod_bobina_kg"],2)?>"> Kg
            </td>
         </tr>
         <?php
      }
      ?>
      <tr>
         <td class="tdleft" valign="top">Comentarios</td>
         <td class="tdnrm" colspan="2">
            <textarea class="inptxt" name="evt_comments" id="evt_comments"
            style="width:100%;height:60px"><?=stripslashes($refevent["evt_comments"])?></textarea>
         </td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      if((int)$_REQUEST["refid"])
      {
         $sql = " select t1.*
                  from prod_mermatypes t1
                  where
                  t1.merma_status > 0
                  order by t1.merma_title";
         $mermatypes = $CON->select($sql);
         foreach($mermatypes AS $mermatype)
         {
            $sql = " select *
                     from prod_worker_ot_defectunits
                     where
                     evt_refid = {$_REQUEST["refid"]} and
                     evt_type  = 'merma' and
                     evt_merma_typeid = {$mermatype["id"]}";
            $mermathisdata = $CON->select($sql);
            $mermathisdata = $mermathisdata[0];
            ?>
            <tr>
               <td class="tdleft">Merma</td>
               <td class="tdnrm" colspan="2">
                  <table border="0" width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                     <td class="tdnrm tdnoborder" width="300"><?=$mermatype["merma_title"]?></td>
                     <td class="tdnrm tdnoborder" width="220">
                        <?php
                        if($_ISSELLADORA || (int)$hasopeninit["equipo_prod_isprinter_flexo"] || (int)$hasopeninit["equipo_prod_isprinter_seri"])
                        {  ?>
                           Kgs&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                           <input type="hidden" name="mermaconvert" value="1">
                           <input type="text" class="inptxt" name="merma_kgs_<?=$mermatype["id"]?>" id="merma_kgs_<?=$mermatype["id"]?>"
                           style="width:120px" autocomplete="off"
                           value="<?if((int)$mermathisdata["id"]) echo printPrice($mermathisdata["evt_kgstounits"])?>">

                           <input type="text" class="inptxt" name="merma_amount_<?=$mermatype["id"]?>" id="merma_amount_<?=$mermatype["id"]?>"
                           style="width:120px;background-color:#EEEEEE;display:none" autocomplete="off"
                           value="">
                           <?php
                        }
                        /*
                        elseif($_ISSELLADORA)
                        {  ?>
                           Mts&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                           <input type="hidden" name="mermamtsconvert" value="1">
                           <input type="text" class="inptxt" name="merma_mts_<?=$mermatype["id"]?>" id="merma_mts_<?=$mermatype["id"]?>"
                           style="width:120px" autocomplete="off"
                           value="<?if((int)$mermathisdata["id"]) echo printPrice($mermathisdata["evt_mtstounits"])?>">

                           <input type="text" class="inptxt" name="merma_amount_<?=$mermatype["id"]?>" id="merma_amount_<?=$mermatype["id"]?>"
                           style="width:120px;background-color:#EEEEEE;display:none" autocomplete="off"
                           value="">
                           <?php
                        }
                        */
                        else
                        {  ?>
                           Cantidad
                           <input type="text" class="inptxt" name="merma_amount_<?=$mermatype["id"]?>" id="merma_amount_<?=$mermatype["id"]?>"
                           style="width:120px" autocomplete="off"
                           value="<?if((int)$mermathisdata["id"]) echo printPrice($mermathisdata["evt_amount"])?>">
                           <?php
                        }
                        ?>
                     </td>
                     <td class="tdnrm tdnoborder">
                        Comentarios
                        <input class="inptxt" name="merma_comments_<?=$mermatype["id"]?>" id="merma_comments_<?=$mermatype["id"]?>"
                        style="width:calc(100% - 100px);" value="<?=stripslashes($mermathisdata["evt_comments"])?>">
                     </td>
                  </tr>
                  </table>
               </td>
            </tr>
            <?php
         }

         //----------------------------------------------------------------------------------
         if(!(int)$hasopeninit["equipo_prod_isprinter_flexo"] && !(int)$hasopeninit["equipo_prod_isprinter_seri"] && !$_ISREBOBINADORA)
         {
            $sql = " select t1.*
                     from prod_repairtypes t1
                     where
                     t1.repair_status > 0
                     order by t1.repair_title";
            $repairtypes = $CON->select($sql);
            foreach($repairtypes AS $repairtype)
            {
               $sql = " select *
                        from prod_worker_ot_defectunits
                        where
                        evt_refid = {$_REQUEST["refid"]} and
                        evt_type  = 'repair' and
                        evt_repair_typeid = {$repairtype["id"]}";
               $repthisdata = $CON->select($sql);
               $repthisdata = $repthisdata[0];
               ?>
               <tr>
                  <td class="tdleft">Reparación</td>
                  <td class="tdnrm" colspan="2">
                     <table border="0" width="100%" cellpadding="0" cellspacing="0">
                     <tr>
                        <td class="tdnrm tdnoborder" width="300"><?=$repairtype["repair_title"]?></td>
                        <td class="tdnrm tdnoborder" width="220">
                           <?php
                           if($_ISSELLADORA)
                           {  ?>
                              Kgs&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                              <input type="hidden" name="repairconvert" value="1">
                              <input type="text" class="inptxt" name="repair_kgs_<?=$repairtype["id"]?>" id="repair_kgs_<?=$repairtype["id"]?>"
                              style="width:120px" autocomplete="off"
                              value="<?if((int)$repthisdata["id"]) echo printPrice($repthisdata["evt_kgstounits"])?>">

                              <input type="text" class="inptxt" name="repair_amount_<?=$repairtype["id"]?>" id="repair_amount_<?=$repairtype["id"]?>"
                              style="width:120px;background-color:#EEEEEE;display:none" autocomplete="off"
                              value="">
                              <?php
                           }
                           else
                           {  ?>
                              Cantidad
                              <input type="text" class="inptxt" name="repair_amount_<?=$repairtype["id"]?>" id="repair_amount_<?=$repairtype["id"]?>" style="width:120px" autocomplete="off"
                              value="<?if((int)$repthisdata["id"]) echo printPrice($repthisdata["evt_amount"])?>">
                              <?php
                           }
                           ?>
                        </td>
                        <td class="tdnrm tdnoborder">
                           Comentarios
                           <input class="inptxt" name="repair_comments_<?=$repairtype["id"]?>" id="repair_comments_<?=$repairtype["id"]?>"
                           style="width:calc(100% - 100px);" value="<?=stripslashes($repthisdata["evt_comments"])?>">
                        </td>
                     </tr>
                     </table>
                  </td>
               </tr>
               <?php
            }
         }
      }

      //----------------------------------------------------------------------------------
      /*
      $sql = " select t1.*, t2.merma_title 'causa'
               from prod_worker_ot_defectunits t1
               INNER JOIN prod_mermatypes t2 ON t1.evt_merma_typeid = t2.id
               where
               t1.evt_refid   = {$_REQUEST["refid"]} and
               t1.evt_type    = 'merma' and
               t1.evt_status  > 0
               UNION ALL
               select t1.*, t2.repair_title 'causa'
               from prod_worker_ot_defectunits t1
               INNER JOIN prod_repairtypes t2 ON t1.evt_repair_typeid = t2.id
               where
               t1.evt_refid   = {$_REQUEST["refid"]} and
               t1.evt_type    = 'repair' and
               t1.evt_status  > 0
               order by 1";
      $defectunits = $CON->select($sql);
      if(count($defectunits) && $defectunits != false)
      {  ?>
         <tr>
            <td class="tdleft" valign="top">Mermas/Reparación</td>
            <td class="tdnrm" colspan="2">
               <table border="0" width="100%" cellpadding="0" cellspacing="0">
               <tr>
                  <td><b>Tipo</b></td>
                  <td><b>Cantidad</b></td>
                  <td><b>Clasificación</b></td>
                  <td><b>Comentarios</b></td>
                  <td width="80"><b>Opción</b></td>
               </tr>
               <?php
               foreach($defectunits AS $defectunit)
               {
                  $xtype = "Merma";
                  $xlink = "fancy.mermas.php";
                  if($defectunit["evt_type"] == "repair")
                  {
                     $xtype   = "Reparación";
                     $xlink   = "fancy.repairs.php";
                  }
                  ?>
                  <tr>
                     <td class="tdnrmtop" style="padding:3px"><?=$xtype?></td>
                     <td class="tdnrmtop" style="padding:3px"><?=printPrice($defectunit["evt_amount"])?></td>
                     <td class="tdnrmtop" style="padding:3px"><?=$defectunit["causa"]?></td>
                     <td class="tdnrmtop" style="padding:3px"><?=$defectunit["evt_comments"]?>&nbsp;</td>
                     <td class="tdnrmtop" style="padding:3px">
                        <div class="btngrey" style="width:80px"
                        onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/<?=$xlink?>?refid=<?=$_REQUEST["refid"]?>&evtid=<?=$defectunit["id"]?>', 'iframe', '500', '300', 'auto')">
                           <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar
                        </div>
                     </td>
                  </tr>
                  <?php
               }
               ?>
               </table>
            </td>
         </tr>
         <?php
      }
      */
      ?>
      </table>
      <div style="clear:both;height:10px"></div>
      <?php
      if((int)$hasopeninit["type_createstock_act"] && (int)$_REQUEST["refid"])
      {
         $sql = " select *
                  from orders
                  where
                  id = {$agenda["ag_reqid"]}";
         $headdata = $CON->select($sql);
         $headdata = $headdata[0];

         $sql = " select *
                  from prod_worker_embalajes
                  where
                  evt_prod_worker_otid = {$hasopenot["id"]} and
                  evt_reqid            = {$hasopenot["prd_reqid"]}";
         $embalajesaved = $CON->select($sql);
         $embalajesaved = $embalajesaved[0];
         ?>
         <input type="hidden" name="embalaje_save_params" value="1">
         <div style="clear:both;height:10px"></div>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="290">
            <col width="220">
            <col>
         </colgroup>
         <tr>
            <td class="tdheader">Medidas / Cantidades embalaje</td>
            <td class="tdheader">Predefinido</td>
            <td class="tdheader">Ingreso</td>
         </tr>
         <tr>
            <td class="tdleft">Medida de caja</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_medidas_caja" style="width:200px;background-color:#EEEEEE" readonly
               value="<?=$headdata["req_embalaje_medidas_caja"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_medidas_caja" style="width:200px;"
               value="<?if($embalajesaved["req_embalaje_medidas_caja"] != "") echo $embalajesaved["req_embalaje_medidas_caja"]; else echo $headdata["req_embalaje_medidas_caja"]?>">
            </td>
         </tr>
         <tr>
            <td class="tdleft">Cantidad de bolsas por caja</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_bolsas_por_caja_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_bolsas_por_caja_amt"]) echo (int)$headdata["req_embalaje_bolsas_por_caja_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_bolsas_por_caja_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_bolsas_por_caja_amt"]) echo $embalajesaved["req_embalaje_bolsas_por_caja_amt"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         <tr>
            <td class="tdleft">Cantidad de cajas por pallets</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_cajas_por_pallet_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_cajas_por_pallet_amt"]) echo (int)$headdata["req_embalaje_cajas_por_pallet_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_cajas_por_pallet_amt" style="width:200px"
               value="<?if((int)$embalajesaved["req_embalaje_cajas_por_pallet_amt"]) echo $embalajesaved["req_embalaje_cajas_por_pallet_amt"]?>" autocomplete="off">
            </td>
         </tr>
         <tr>
            <td class="tdleft">Cantidad de pallets completos</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_pallets_completos_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_pallets_completos_amt"]) echo (int)$headdata["req_embalaje_pallets_completos_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_pallets_completos_amt" style="width:200px"
               value="<?if((int)$embalajesaved["req_embalaje_pallets_completos_amt"]) echo $embalajesaved["req_embalaje_pallets_completos_amt"]?>" autocomplete="off">
            </td>
         </tr>
         <tr>
            <td class="tdleft">Numero de cajas en pallet incompleto</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_palletcajas_incompletos_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_palletcajas_incompletos_amt"]) echo (int)$headdata["req_embalaje_palletcajas_incompletos_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_palletcajas_incompletos_amt" style="width:200px"
               value="<?if((int)$embalajesaved["req_embalaje_palletcajas_incompletos_amt"]) echo $embalajesaved["req_embalaje_palletcajas_incompletos_amt"]?>" autocomplete="off">
            </td>
         </tr>
         <tr>
            <td class="tdleft">Cantidad total de cajas completas</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_cajas_completas_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_cajas_completas_amt"]) echo (int)$headdata["req_embalaje_cajas_completas_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_cajas_completas_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_cajas_completas_amt"]) echo $embalajesaved["req_embalaje_cajas_completas_amt"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         <tr>
            <td class="tdleft">Caja final (completa pedido)</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_caja_final" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_caja_final"]) echo (int)$headdata["req_embalaje_caja_final"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_caja_final" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_caja_final"]) echo $embalajesaved["req_embalaje_caja_final"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         <tr>
            <td class="tdleft">Bolsas sobrantes</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" style="width:200px;background-color:#EEEEEE" readonly value="" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_bolsas_sobrantes_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_bolsas_sobrantes_amt"]) echo $embalajesaved["req_embalaje_bolsas_sobrantes_amt"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         </table>
         <div style="clear:both;height:20px"></div>
         <?php
      }

      if((int)$_REQUEST["refid"])
      {  ?>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td width="50%" style="padding-right:5px">
               <div class="btngrey" onclick="location.href = 'prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';"
               style="float:left;width:40%">
                  <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
               </div>
               <div class="btnred" onclick="if(askDel('')) { document.xform_inp.mode.value='eventdelete'; document.xform_inp.submit(); }"
               style="margin-left:5px;float:left;width:40%">
                  <i class="fa fa-fw fa-times-circle" style="color:white;"></i> Eliminar&nbsp;
               </div>
            </td>
            <td width="50%" style="padding-left:5px">
               <?php
               if(!(int)$refevent["evt_enddat"])
               {  ?>
                  <div class="btnorange" onclick="document.xform_inp.submode.value='end';submitForm(document.xform_inp)"
                  style="margin-left:5px;float:right;width:40%">
                     <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar producción&nbsp;
                  </div>
                  <div class="btngrey" onclick="submitForm(document.xform_inp)"
                  style="float:right;width:40%">
                     <i class="fa fa-fw fa-save" style="color:white;"></i> Actualizar&nbsp;
                  </div>
                  <?php
               }
               else
               {  ?>
                  <div class="btngreen" onclick="submitForm(document.xform_inp)">
                     <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
                  </div>
                  <?php
               }
               ?>
            </td>
         </tr>
         </table>
         <?php
      }
      else
      {  ?>
         <div class="btngreen" onclick="submitForm(document.xform_inp)">
            <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar producción&nbsp;
         </div>
         <?php
      }
      ?>
   </td>
</tr>
</table>
</form>
<script language="JavaScript">
   $(document).ready(function()
   {
      <?php
      if(!$_EMBALAJE_PROD_CALCINP)
      {  ?>
         $('#prod_amount').focus();
         <?php
      }
      ?>
   });
   function calcEmbalajeProdAmt()
   {
      var bolsas_por_caja_amt    = getIntegerFromValue(document.xform_inp.req_embalaje_bolsas_por_caja_amt.value);
      var cajas_completas_amt    = getIntegerFromValue(document.xform_inp.req_embalaje_cajas_completas_amt.value);
      var bolsas_sobrantes_amt   = getIntegerFromValue(document.xform_inp.req_embalaje_bolsas_sobrantes_amt.value);
      var bolsas_caja_final_amt  = getIntegerFromValue(document.xform_inp.req_embalaje_caja_final.value);
      var totalprod              = parseInt((bolsas_por_caja_amt * cajas_completas_amt) +bolsas_sobrantes_amt +bolsas_caja_final_amt);

      $('#prod_amount').val(totalprod);
   }
   function calcUnitsFromPasadas()
   {
      var pasadas_inp      = getIntegerFromValue($('#idx_seri_pasadas_inp').val());
      var bastidor_inp     = getIntegerFromValue($('#idx_bastidor_amt').val());
      var totalpasprod     = parseInt((pasadas_inp * bastidor_inp));

      $('#prod_amount').val(totalpasprod);
   }
   function getIntegerFromValue(calval)
   {
      if(calval == '') calval = '0';
      while(calval.indexOf(".") > 0) calval = calval.replace(".", "");
      if(calval.indexOf("0") > 0) while(calval.substr(0,1) == '0') calval = calval.substr(1);
      calval = parseInt(calval);
      if(isNaN(calval)) calval = 0;
      return calval;
   }
</script>
<?php