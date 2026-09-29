<?php
$_REQUEST["agid"] = (int)$_REQUEST["agid"];
// $_REQUEST["refid"] = (int)$hasopenot["id"];
// $_REQUEST["agidprueba"] = "5";


$sql = "select id, concat(wrk_firstname,' ',wrk_lastname) as ayudantes FROM workers
where  wrk_cargoid not in(4,10)
   and wrk_turno_state = 1
   and wrk_status > 0";
$ayudantes = $CON->select($sql);

if((int)$_REQUEST["refid"])
{
   $sql = " select *
            from prod_worker_ot_events
            where
            id = {$_REQUEST["refid"]}";
   $refevent = $CON->select($sql);
   $refevent = $refevent[0];
}

$sql = " select t1.id, t1.med_name, t1.med_crtdat
         from prod_medidas t1
         where
         t1.med_status > 0
         order by t1.med_name ";
$meds = $CON->select($sql);
?>
<form action="prodwrk.php" method="post" name="xform_inp" id="xform_inp"
onsubmit="<?if((int)$hasopeninit["type_ant_inpmedidas_act"])
{  ?>
   return checkform(new Array(this.evt_medida_fromid, this.evt_medida_toid));
   <?php
}
else
{  ?>
   return true;
   <?php
}
?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="aperturasave">
<input type="hidden" name="submode" value="">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">

<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="6" cellspacing="0">
      <colgroup>
         <col width="150">
         <col>
      </colgroup>
      <tr>
         <td class="tdleft">Tipo</td>
         <td class="tdnrm"><?=$hasopeninit["type_ant_title"]?></td>
      </tr>
      <tr>
         <td class="tdleft">Maquina</td>
         <td class="tdnrm"><?=$hasopeninit["equipo_name"]?></td>
      </tr>
      <?php
      if((int)$hasopeninit["type_ant_inpmedidas_act"])
      {  ?>
         <tr>
            <td class="tdleft">De Medida *</td>
            <td class="tdnrm">
               <select name="evt_medida_fromid" class="inptxt" style="width:400px;background-color:#FFFFFF">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($meds AS $med)
                  {  ?>
                     <option value="<?=$med["id"]?>"
                     <?if($med["id"] == $refevent["evt_medida_fromid"]) echo "selected"?>>
                        <?=$med["med_name"]?>
                     </option>
                     <?php
                  }
                  ?> 
               </select>
            </td>
         </tr>
         <tr>
            <td class="tdleft">A Medida *</td>
            <td class="tdnrm">
               <select name="evt_medida_toid" class="inptxt" style="width:400px;background-color:#FFFFFF">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($meds AS $med)
                  {  ?>
                     <option value="<?=$med["id"]?>"
                     <?if($med["id"] == $refevent["evt_medida_toid"]) echo "selected"?>>
                        <?=$med["med_name"]?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <?php
      }
      ?>
      <tr>
         <td class="tdleft">Ayudante</td>
         <td class="tdnrm">
            <select name="evt_idayudante" class="inptxt" style="width:400px;background-color:#FFFFFF">
                  <option value="0">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($ayudantes AS $ayudante)
                  {  ?>
                     <option value="<?=$ayudante["id"]?>"
                     <?if($ayudante["id"] == $refevent["evt_idayudante"]) echo "selected"?>>
                        <?=$ayudante["ayudantes"]?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
         </td>
      </tr>
      <tr>
         <td class="tdleft" valign="top">Comentarios</td>
         <td class="tdnrm">
            <textarea class="inptxt" name="evt_comments" id="evt_comments"
            style="width:100%;height:60px"><?=stripslashes($refevent["evt_comments"])?></textarea>
         </td>
      </tr>
      </table>
      <div style="clear:both;height:10px"></div>
      <?php
      if((int)$_REQUEST["refid"])
      {  ?>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td width="50%" style="padding-right:5px">
               <div class="btngrey" onclick="location.href = 'prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';"
               style="float:left;width:40%">
                  <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
               </div>
            </td>
            <td width="50%" style="padding-left:5px" align="right">
               <?php
               if(!(int)$refevent["evt_enddat"])
               {  ?>
                  <div class="btnorange" onclick="document.xform_inp.submode.value='end';submitForm(document.xform_inp)"
                     style="margin-left:5px;float:right;width:25%">
                     <i class="fa fa-fw fa-stop" style="color:white;"></i> Ir a Autocontrol
                  </div>
                  <div class="btngrey" onclick="submitForm(document.xform_inp)"
                     style="margin-left:5px;float:right;width:25%">
                     <i class="fa fa-fw fa-save" style="color:white;"></i> Actualizar
                  </div>
                  <div class="btnred" onclick="document.xform_inp.submode.value='cerrar';submitForm(document.xform_inp)"
                     style="margin-left:5px;float:right;width:25%">
                     <i class="fa fa-fw fa-check" style="color:white;"></i> Terminar
                  </div>                  
                  <?php
               }
               else
               {  ?>
                  <div class="btngreen" onclick="submitForm(document.xform_inp)" style="width:40%">
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
            <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar alistamiento&nbsp;
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
      $('#evt_comments').focus();
   });
</script>
<?php