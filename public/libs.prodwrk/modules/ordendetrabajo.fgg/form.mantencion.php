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

$sql = " select *
         from equipo_manttype
         where
         mant_status > 0
         order by mant_title";
$manttypes = $CON->select($sql);

$sql = " select t1.*
         from equipos_ubimants t1
         INNER JOIN equipos_ubimants_types t2 ON t1.id = t2.ubimid
         where
         t1.ubim_status > 0 and
         t2.typeid = {$hasopeninit["equipo_type_id"]}
         order by t1.ubim_title";
$equipos_ubimants = $CON->select($sql);
?>
<form action="prodwrk.php" method="post" name="xform_inp" id="xform_inp"
onsubmit="return checkform(new Array(this.evt_equipo_mantid, this.evt_ubim_id))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="mantencionsave">
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
         <td class="tdleft">Máquina</td>
         <td class="tdnrm"><?=$hasopeninit["equipo_name"]?></td>
      </tr>
      <tr>
         <td class="tdleft">Mantención</td>
         <td class="tdnrm">
            <select name="evt_equipo_mantid" class="inptxt" style="width:400px;background-color:#FFFFFF">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($manttypes AS $manttype)
               {  ?>
                  <option value="<?=$manttype["id"]?>"
                  <?if($manttype["id"] == $refevent["evt_equipo_mantid"]) echo "selected"?>>
                     <?=$manttype["mant_title"]?> (Código <?=$manttype["mant_code"]?>)
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="tdleft">Ubicación</td>
         <td class="tdnrm">
            <select name="evt_ubim_id" class="inptxt" style="width:400px;background-color:#FFFFFF">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($equipos_ubimants AS $equipos_ubimant)
               {  ?>
                  <option value="<?=$equipos_ubimant["id"]?>"
                  <?if($equipos_ubimant["id"] == $refevent["evt_ubim_id"]) echo "selected"?>>
                     <?=$equipos_ubimant["ubim_title"]?>
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
                     <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar 
                     &nbsp;
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
            <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar mantención&nbsp;
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
      
   });
</script>
<?php