<?php
//----------------------------------------------------------------------------------
$sql_day    = (int)date("d");
$sql_month  = (int)date("m");
$sql_year   = (int)date("Y");
$_REQUEST["agid"] = (int)$_REQUEST["agid"];

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "init")
{
   $sql = " select *
            from prod_worker_init
            where
            win_wrkid      = {$_SESSION["wrk_id"]} and
            win_plantaid   = {$_SESSION["user_planta_id"]} and
            win_status     = 1";
   $hasopen = $CON->select($sql);
   $hasopen = $hasopen[0];

   $currtme                = time();
   $_REQUEST["equipoid"]   = (int)$_REQUEST["equipoid"];
   $_REQUEST["assid"]      = (int)$_REQUEST["assid"];

   $sql = " select *
            from prod_worker_init
            where
            win_wrkid      = {$_SESSION["wrk_id"]} and
            win_plantaid   = {$_SESSION["user_planta_id"]} and
            win_equipoid   = {$_REQUEST["equipoid"]} and
            win_status     = 3";
   $hasopenequipo = $CON->select($sql);
   $hasopenequipo = $hasopenequipo[0];

   if((int)$hasopenequipo["id"])
   {
      $sql = " update prod_worker_init
               set
               win_status = 1
               where
               id = {$hasopenequipo["id"]}";
      $CON->no_result($sql);
   }
   else
   {
      $sql = " insert into prod_worker_init
               (win_crtdat, win_wrkid, win_status, win_plantaid, win_equipoid,
                win_ass_id, win_day, win_month, win_year)
               VALUES
               ({$currtme}, {$_SESSION["wrk_id"]}, 1, {$_SESSION["user_planta_id"]}, {$_REQUEST["equipoid"]},
                {$_REQUEST["assid"]}, {$sql_day}, {$sql_month}, {$sql_year})";
      $CON->no_result($sql);
   }

   //2025-06-21: PAUSE WRKINIT
   if((int)$hasopen["id"])
   {
      $sql = " update prod_worker_init
               set
               win_status = 3
               where
               id = {$hasopen["id"]}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "end")
{
   $hasopeninit = getProdOpenWorkerInit($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);
   if((int)$hasopeninit["id"])
   {
      $currtme = time();
      $sql = " update prod_worker_init
               set
               win_enddat  = {$currtme},
               win_status  = 2
               where
               id = {$hasopeninit["id"]}";
      $CON->no_result($sql);
   }

   $sql = " update prod_worker_init
            set
            win_status     = 1
            where
            win_wrkid      = {$_SESSION["wrk_id"]} and
            win_status     = 3 and
            win_plantaid   = {$_SESSION["user_planta_id"]}
            order by id desc
            LIMIT 1";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "cerrar")
{
   /*
   $hasopeninit = getProdOpenWorkerInit($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);

   $currtme = time();
   $sql = "update prod_worker_ot set wok_enddat = {$currtme} , wok_status = 2 where wok_ag_id = {$_REQUEST["agid"]} and wok_init_id = {$_REQUEST["initid"]} and wok_status = 1 ";
   
   $xres = $CON->no_result($sql);
   if($xres)
   {  
      ?>
      <script language="JavaScript">
         location.href = 'prodwrk.php?mid=1';
      </script>
      <?php
      exit;
   }
   */

}


//
// echo("wrk_id : ".$_SESSION["wrk_id"]." agid : ".$_REQUEST["agid"]." planta ".$_SESSION["user_planta_id"]);

//----------------------------------------------------------------------------------
$hasopeninit = getProdOpenWorkerInit($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);
$hasopenot   = getProdOpenWorkerOT($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.turn_name, t5.inc_name_short, t5.inc_color, t5.inc_color_dom, t6.type_libre_act,
                t5.inc_type, t4.cfi_inc_id, t7.equipo_name, t6.type_init_hour 'assign_hr_inithr',
                t6.type_init_min 'assign_hr_initmin',
                t6.type_end_hour 'assign_hr_endhr', t6.type_end_min 'assign_hr_endmin',
                t1.assign_equipoaid, t8.type_ant_title
         from turnos_config_assign t1
         INNER JOIN turnos t3                            ON t1.assign_turno_id = t3.id
         LEFT OUTER JOIN turnos_config_incidencias t4    ON t1.incidencia_cfi_id = t4.id
         LEFT OUTER JOIN incidencias t5                  ON t4.cfi_inc_id = t5.id
         LEFT OUTER JOIN turnos_types t6                 ON t1.assign_turno_type_id = t6.id
         LEFT OUTER JOIN equipo t7                       ON t1.assign_equipoaid = t7.id
         LEFT OUTER JOIN equipo_type t8                  ON t7.equipo_type_id = t8.id
         where
         t1.assign_day        = {$sql_day} and
         t1.assign_month      = {$sql_month} and
         t1.assign_year       = {$sql_year} and
         t1.assign_planta_id  = {$_SESSION["user_planta_id"]} and
         t1.assign_worker_id  = {$_SESSION["wrk_id"]} and
         t8.type_ant_prod_dabl = 0 and
         t7.equipo_prod_dabl = 0
         order by t3.turn_name, t7.equipo_name, t1.assign_special_slot, t1.assign_worker_id desc, t6.type_libre_act desc";
$assdata = $CON->select($sql);



$_SQL_EXLUDE_EQUIPOIDS = "";
foreach($assdata AS $assdatarow)
   $_SQL_EXLUDE_EQUIPOIDS .= $assdatarow["assign_equipoaid"].",";
$_SQL_EXLUDE_EQUIPOIDS = substr($_SQL_EXLUDE_EQUIPOIDS, 0, -1);


//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.equipo_name, t8.type_ant_title
         from equipo t1
         LEFT OUTER JOIN equipo_type t8 ON t1.equipo_type_id = t8.id
         where
         t1.equipo_status > 0 and
         t8.type_ant_prod_dabl = 0 and
         t1.equipo_prod_dabl = 0  ";
if($_SQL_EXLUDE_EQUIPOIDS != "")
   $sql .= " and t1.id NOT IN ({$_SQL_EXLUDE_EQUIPOIDS})";
$sql .= " order by t8.type_ant_title, t1.equipo_name";

$othereqs = $CON->select($sql);


function getProdOpenWorkerOTId($CON, $wrkid, $plantaid, $idagid)
{
   $hasopeninit = getProdOpenWorkerInit($CON, $wrkid, $plantaid);
   if((int)$hasopeninit["id"])
   {
      $sql = " select t1.*, t4.prd_number, t4.prd_reqid
                 from prod_worker_ot t1
                 INNER JOIN prod_worker_init t2   ON t1.wok_init_id = t2.id
                 INNER JOIN prod_agenda t3        ON t1.wok_ag_id = t3.id
                 INNER JOIN prod_header t4        ON t3.ag_prdid = t4.id
                 where
                 t1.wok_init_id = {$hasopeninit["id"]} and
                 t1.wok_ag_id   = {$idagid} and
                 t1.wok_status  = 1";

      $hasopenot = $CON->select($sql);
      $hasopenot = $hasopenot[0];

  
      return $hasopenot;
   }
   return false;
}

?>
<font style="font-size:20px"><b>Iniciar / Terminar turno</b></font>
<div style="clear:both;height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;padding:10px;color:#666666;font-size:14px;border-radius:5px;padding:20px">
      <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Asignación máquina
      <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
      Iniciar / Terminar turno
   </td>
</tr>
</table>
<div style="clear:both;height:10px"></div>
<?php

$sql = " select distinct win_equipoid
         from prod_worker_init t1
         where
         t1.win_wrkid      = {$_SESSION["wrk_id"]} and
         t1.win_status     = 3 and
         t1.win_plantaid   = {$_SESSION["user_planta_id"]}";
$allpendings = $CON->select($sql);
foreach($allpendings AS $allpending)
   $_ALLPENDINGS[$allpending["win_equipoid"]] = 1;

if((int)$hasopeninit["id"])
{  ?>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col width="120">
            <col>
         </colgroup>
         <tr>
            <td class="tdleft">Fecha inicio</td>
            <td class="tdnrm"><?=date("d.m.Y", $hasopeninit["win_crtdat"])?></td>
         </tr>
         <tr>
            <td class="tdleft">Hora inicio</td>
            <td class="tdnrm"><?=date("H:i:s", $hasopeninit["win_crtdat"])?></td>
         </tr>
         <tr>
            <td class="tdleft">Tipo</td>
            <td class="tdnrm"><?=$hasopeninit["type_ant_title"]?></td>
         </tr>
         <tr>
            <td class="tdleft">Máquina</td>
            <td class="tdnrm"><?=$hasopeninit["equipo_name"]?></td>
         </tr>
         </table>
         <div style="clear:both;height:10px"></div>
         <?php
         
         if((int)$hasopenot["id"])
         {  ?>
            <div style="clear:both;height:10px"></div>
            <b class="msg_save_err">
               <i class="fa fa-fw fa-exclamation-triangle"></i>
               Termino del turno no disponible, mientras la OT <b><?=$hasopenot["prd_number"]?> se encuentra activada.
            </b>
            <?php
         }
         else
         {  ?>
            <div class="btnred"
            onclick="location.href='prodwrk.php?exec=end&refid=<?=$hasopeninit[$x]["id"]?>'">
               <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar turno&nbsp;
            </div>
            <?php
            if((int)$_REQUEST["autoclose"])
            {  ?>
               <script language="Javascript">
                  location.href='prodwrk.php?exec=end&refid=<?=$hasopeninit[$x]["id"]?>'
               </script>
               <?php
               exit;
            }
         }
         ?>
      </td>
   </tr>
   </table>

   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col>
            <col>
            <col width="150">
            <col width="130">
            <col width="130">
            <col width="160">
         </colgroup>
         <tr>
            <td class="tdheader">Tipo</td>
            <td class="tdheader">Máquina</td>
            <td class="tdheader">Turno</td>
            <td class="tdheader" align="center">Horario</td>
            <td class="tdheader" align="center">Tipo</td>
            <td class="tdheader" align="center">Opciones</td>
         </tr>
         <?php
         for($x = 0; $x < count($assdata) && $assdata != false; $x++)
         {  ?>
            <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="tdnrm"><?=$assdata[$x]["type_ant_title"]?></td>
               <td class="tdnrm"><?=$assdata[$x]["equipo_name"]?></td>
               <td class="tdnrm"><?=$assdata[$x]["turn_name"]?></td>
               <td class="tdnrm" align="center">
                  <?=sprintf("%02s", $assdata[$x]["ass_init_hour"])?>:<?=sprintf("%02s", $assdata[$x]["ass_init_min"])?>
                  -
                  <?=sprintf("%02s", $assdata[$x]["ass_end_hour"])?>:<?=sprintf("%02s", $assdata[$x]["ass_end_min"])?>
               </td>
               <td class="tdnrm" align="center"><b class="msg_save_ok">Planificado</b></td>
               <td class="tdnrm" align="center">
                  <?php
                  if($assdata[$x]["assign_equipoaid"] == $hasopeninit["win_equipoid"])
                  {  ?>
                     <div class="btngreen">
                        <i class="fa fa-fw fa-clock" style="color:white;"></i> En curso&nbsp;
                     </div>
                     <?php
                  }
                  else
                  {
                     if((int)$_ALLPENDINGS[$assdata[$x]["assign_equipoaid"]])
                     {  ?>
                        <div class="btngreen" style="background-color:#16AFC6"
                        onclick="location.href='prodwrk.php?exec=init&equipoid=<?=$assdata[$x]["assign_equipoaid"]?>&assid=<?=$assdata[$x]["id"]?>'">
                           <i class="fa fa-fw fa-clock" style="color:white;"></i> En curso&nbsp;
                        </div>
                        <?php

                     }
                     else
                     {  ?>
                        <div class="btngreen"
                        onclick="location.href='prodwrk.php?exec=init&equipoid=<?=$assdata[$x]["assign_equipoaid"]?>&assid=<?=$assdata[$x]["id"]?>'">
                           <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar turno&nbsp;
                        </div>
                        <?php
                     }
                  }
                  ?>
               </td>
            </tr>
            <?php
         }
         if(count($othereqs) && $othereqs != false)
         {
            for($x = 0; $x < count($othereqs) && $othereqs != false; $x++)
            {  ?>
               <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="tdnrm"><?=$othereqs[$x]["type_ant_title"]?></td>
                  <td class="tdnrm"><?=$othereqs[$x]["equipo_name"]?></td>
                  <td class="tdnrm"><?=$othereqs[$x]["turn_name"]?></td>
                  <td class="tdnrm" align="center"></td>
                  <td class="tdnrm" align="center"><b class="msg_save_err">Sin turno</b></td>
                  <td class="tdnrm" align="center">
                     <?php
                     if($othereqs[$x]["id"] == $hasopeninit["win_equipoid"])
                     {  ?>
                        <div class="btngreen">
                           <i class="fa fa-fw fa-clock" style="color:white;"></i> En curso&nbsp;
                        </div>
                        <?php
                     }
                     else
                     {
                        if((int)$_ALLPENDINGS[$othereqs[$x]["id"]])
                        {  ?>
                           <div class="btngreen" style="background-color:#16AFC6"
                           onclick="location.href='prodwrk.php?exec=init&equipoid=<?=$othereqs[$x]["id"]?>&assid=0'">
                              <i class="fa fa-fw fa-clock" style="color:white;"></i> En curso&nbsp;
                           </div>
                           <?php

                        }
                        else
                        {  ?>
                           <div class="btnorange"
                           onclick="if(askDel('')) { location.href='prodwrk.php?exec=init&equipoid=<?=$othereqs[$x]["id"]?>&assid=0'; }">
                              <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar turno&nbsp;
                           </div>
                           <?php
                        }
                     }
                     ?>
                  </td>
               </tr>
               <?php
            }
         }
         ?>
         </table>
      </td>
   </tr>
   </table>
   <?php
}
else
{  ?>
   <table border="0" width="100%" cellpadding="0" cellspacing="0">
   <tr>
      <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
         <table border="0" width="100%" cellpadding="6" cellspacing="0">
         <colgroup>
            <col>
            <col>
            <col width="150">
            <col width="130">
            <col width="130">
            <col width="160">
         </colgroup>
         <tr>
            <td class="tdheader">Tipo</td>
            <td class="tdheader">Máquina</td>
            <td class="tdheader">Turno</td>
            <td class="tdheader" align="center">Horario</td>
            <td class="tdheader" align="center">Tipo</td>
            <td class="tdheader" align="center">Opciones</td>
         </tr>
         <?php
         for($x = 0; $x < count($assdata) && $assdata != false; $x++)
         {  ?>
            <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="tdnrm"><?=$assdata[$x]["type_ant_title"]?></td>
               <td class="tdnrm"><?=$assdata[$x]["equipo_name"]?></td>
               <td class="tdnrm"><?=$assdata[$x]["turn_name"]?></td>
               <td class="tdnrm" align="center">
                  <?=sprintf("%02s", $assdata[$x]["ass_init_hour"])?>:<?=sprintf("%02s", $assdata[$x]["ass_init_min"])?>
                  -
                  <?=sprintf("%02s", $assdata[$x]["ass_end_hour"])?>:<?=sprintf("%02s", $assdata[$x]["ass_end_min"])?>
               </td>
               <td class="tdnrm" align="center"><b class="msg_save_ok">Planificado</b></td>
               <td class="tdnrm" align="center">
                  <div class="btngreen"
                  onclick="location.href='prodwrk.php?exec=init&equipoid=<?=$assdata[$x]["assign_equipoaid"]?>&assid=<?=$assdata[$x]["id"]?>'">
                     <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar turno&nbsp;
                  </div>
               </td>
            </tr>
            <?php
         }
         if(count($othereqs) && $othereqs != false)
         {
            for($x = 0; $x < count($othereqs) && $othereqs != false; $x++)
            {  ?>
               <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="tdnrm"><?=$othereqs[$x]["type_ant_title"]?></td>
                  <td class="tdnrm"><?=$othereqs[$x]["equipo_name"]?></td>
                  <td class="tdnrm"><?=$othereqs[$x]["turn_name"]?></td>
                  <td class="tdnrm" align="center"></td>
                  <td class="tdnrm" align="center"><b class="msg_save_err">Sin turno</b></td>
                  <td class="tdnrm" align="center">
                     <div class="btnorange"
                     onclick="if(askDel('')) { location.href='prodwrk.php?exec=init&equipoid=<?=$othereqs[$x]["id"]?>&assid=0'; }">
                        <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar turno&nbsp;
                     </div>
                  </td>
               </tr>
               <?php
            }
         }
         ?>
         </table>
      </td>
   </tr>
   </table>
   <?php
}