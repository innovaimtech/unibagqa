<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

require_once("functions.erp.php");
require_once("functions.erp.trans.php");
require_once("functions.printraw.php");

//----------------------------------------------------------------------------------
function getUserRespSthids($CON)
{
   $sql = " select distinct st_id
            from company_shops_storehouses_rspuids
            where
            user_id = {$_SESSION["user_id"]}";
   $rspstids = $CON->select($sql);
   foreach($rspstids AS $rspstid)
      $_RSPSTHIDS[$rspstid["st_id"]] = 1;
   $_RSPSTHIDS_SQL = implode(",", array_keys($_RSPSTHIDS));

   $_RET["_RSPSTHIDS"]     = $_RSPSTHIDS;
   $_RET["_RSPSTHIDS_SQL"] = $_RSPSTHIDS_SQL;

   return $_RET;
}

//----------------------------------------------------------------------------------
function sendV2PordSupervisorNotify($CON, $hasopenot, $hasopeninit, $agenda)
{
   global $_CONFIG;

   $sql = " select t1.*
            from user t1
            INNER JOIN user_group t2 ON t1.id = t2.user_id and t2.group_id IN ({$_CONFIG["_PROD_SUPERVISOR_ROLEID"]})
            where
            t1.user_status = 1 and
            t1.user_mail   like '%@%'";
   $supers = $CON->select($sql);

   if(count($supers) && $supers != false)
   {
      $title = "Solicitud aprobacion partida: CC {$agenda["req_number"]}, OT {$agenda["prd_number"]}";

      $postxt = '<table width="800" cellpadding="3" cellspacing="0" border="0" style="border-right:1px solid #CCCCCC;border-top:1px solid #CCCCCC;">
                  <colgroup>
                     <col width="120">
                     <col width="40%">
                     <col width="120">
                     <col width="40%">
                  </colgroup>
                  <tr>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">N� OT</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.$agenda["prd_number"].'</td>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">N� CC</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.$agenda["req_number"].'</td>
                  </tr>
                  <tr>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Cliente</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.$agenda["cust_name"].'</td>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Producto</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.$agenda["item_number_prod"].' | '.$agenda["item_title"].'</td>
                  </tr>
                  <tr>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Medidas</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.(int)$agenda["fab_med_width"].' x '.(int)$agenda["fab_med_height"].'</td>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Fuelle</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.(int)$agenda["fab_med_fuelle"].'</td>
                  </tr>
                  <tr>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Area</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.(int)$agenda["fab_print_width"].' x '.(int)$agenda["fab_print_height"].'</td>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Tela</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.$agenda["fabric_color"].'</td>
                  </tr>
                  <tr>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Manillas</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.$agenda["manilla_color"].'</td>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Colores</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.$printcolors.'</td>
                  </tr>
                  <tr>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">Cantidad</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">'.printPrice($agenda["ag_amount"]).'</td>
                     <td style="font-weight:bold;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">&nbsp;</td>
                     <td style="border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666">&nbsp;</td>
                  </tr>
                  </table>';
      $body  = "<html>
                <body leftmargin='0' marginwidth='0' topmargin='0' marginheight='0' rightmargin='0' offset='0'>
                <b>{$hasopeninit["type_ant_title"]}: {$hasopeninit["equipo_name"]}</b><br><br>
                {$postxt}
                <br>";

      foreach($supers AS $super)
      {
         sendExternalMail($title, $body, $super["user_mail"], $super["user_firstname"]." ".$super["user_lastname"]);
      }
   }
}

//----------------------------------------------------------------------------------
function getIncidencias($CON, $type = "")
{
   $sql = " select t1.*
            from incidencias t1
            where
            t1.inc_status > 0 ";
   if($type == "inc")
      $sql .= " and t1.inc_type = 0 ";
   if($type == "cambio")
      $sql .= " and t1.inc_type = 1 ";
   if($type == "termino")
      $sql .= " and t1.inc_type = 2 ";
   $sql .= " order by t1.inc_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function printAssignMonthLine($CON, $workerrow, $_CFGACT, $_TTYPES, $date_init, $date_end, $contractrow, $clsname, $onlyincid = 0, $xmain = "")
{
   global $_sesmodulename;
   global $bordercss;
   
   $rowHasDiasPorCubrir = false;
   $rowIsDiasPorCubrir  = false;

   foreach($workerrow["ASSIGNDATA"] AS $workerrowitem)
   {
      $dayidx = date("d.m.Y", $workerrowitem["assign_stamp"]);

      if(!(int)$workerrowitem["assign_special_slot_fromcfi_id"] || (int)$workerrowitem["assign_worker_id"])
      {
         $_ASSIGNDATA[$dayidx] = $workerrowitem;
         $_ASSIGNDATA_CC[$dayidx]++;
      }
      else
      {
         if($_REQUEST["_MULTIMODE_CUBRIR"] && (int)$workerrowitem["assign_special_slot_fromcfi_id"] && !(int)$workerrowitem["assign_worker_id"])
         {
            $_ASSIGNDATA[$dayidx] = $workerrowitem;
            $_ASSIGNDATA_CC[$dayidx]++;
         }
      }
   }
   $REEMPLAZARLINEIDS = $workerrow["_REEMPLAZARLINEIDS"];
   if((int)$onlyincid)
   {
      
      unset($REEMPLAZARLINEIDS);
      unset($_ASSIGNDATA);
      unset($_ASSIGNDATA_CC);

      foreach($workerrow["_REEMPLAZARLINES"][$onlyincid] AS $workerrowitem)
      {
         $dayidx = date("d.m.Y", $workerrowitem["assign_stamp"]);
         $_ASSIGNDATA[$dayidx] = $workerrowitem;
         $_ASSIGNDATA_CC[$dayidx]++;
      }
   }

   $hideFullLine = true;
   $isfirst = true;
   for($x = $date_init+3600; $x <= $date_end; $x += 86400)
   {
      $bcss          = "";
      $dayofweekidx  = date("N", $x);
      if($dayofweekidx == 7)
         $bcss .= "background-color:#EEEEEE;";
      else
         $bcss .= "background-color:#FFFFFF;";

      $celldata      = "&nbsp;";
      $cellidx       = date("d.m.Y", $x);
      $hasCellData   = false;
      $basecolor     = "";
      if((int)$_ASSIGNDATA[$cellidx]["id"])
      {
         $celldata   = $_TTYPES[$_ASSIGNDATA[$cellidx]["assign_turno_type_id"]]["type_name_short"];
         $statdata   = $_TTYPES[$_ASSIGNDATA[$cellidx]["assign_turno_type_id"]]["type_name"];
         if(date("N", $x) == 7)
         {
            $bcss .= "background-color:{$_TTYPES[$_ASSIGNDATA[$cellidx]["assign_turno_type_id"]]["type_color_dom"]} !important;";
            $basecolor = $_TTYPES[$_ASSIGNDATA[$cellidx]["assign_turno_type_id"]]["type_color_dom"];
         }
         else
         {
            $bcss .= "background-color:{$_TTYPES[$_ASSIGNDATA[$cellidx]["assign_turno_type_id"]]["type_color"]} !important;";
            $basecolor = $_TTYPES[$_ASSIGNDATA[$cellidx]["assign_turno_type_id"]]["type_color"];
         }
         $hasCellData = true;
      }

      $brdcls = "";
      if($isfirst)
      {
         $brdcls  = "borderleft";
         $isfirst = false;
      }

      $hidecss = "";
      if($_REQUEST["_VISTADAYMODE"] && $x < $_REQUEST["_CURRENTDAY_STAMP"])
         $hidecss .= ";display:none;";

      if($_REQUEST["_MULTIMODE"])
      {
         if($hasCellData)
            $_TOTALDAYS++;
      }

      if((int)$_ASSIGNDATA[$cellidx]["id"] && !(int)$_ASSIGNDATA[$cellidx]["assign_worker_id"] && !(int)$REEMPLAZARLINEIDS[$_ASSIGNDATA[$cellidx]["id"]])
      {  ?>
         <td class="content_row_os content_tbl_subheader xdelcell <?=$brdcls?>" align="center" title="<?=$cellidx?>"
         style="<?=$bordercss?><?=$hidecss?><?=$bcss?>padding:2px;font-weight:normal;font-size:11px;cursor:pointer;<?if($hasCellData) echo 'border:1px dashed red';?>"
            <?php
            if(!(int)$_ASSIGNDATA[$cellidx]["type_libre_act"])
            {  ?>
               onclick="showFancybox('/iframe.fancy.php?mid=<?=$_REQUEST["mid"]?>&module=workerselectcubrir&cubrirspecialslotid=<?=$_ASSIGNDATA[$cellidx]["id"]?>','iframe', 900, 600, 'auto')"
               <?php
            }
            ?>>
            <?php
            if((int)$_ASSIGNDATA_CC[$cellidx] > 1)
               echo "<div style=position:relative><div style='background-color:red;width:12px;height:8px;font-weight:bold;margin-left:-2px;margin-top:8px;position:absolute;font-size:8px;color:white'>{$_ASSIGNDATA_CC[$cellidx]}</div></div>";
            ?>
            <?=$celldata?>
         </td>
         <?php
         $rowIsDiasPorCubrir = true;
         if($hidecss == "")
            $rowHasDiasPorCubrir = true;
      }
      //SLOTS POR REEMPLAZAR / NO HACER NADA AQUI
      elseif((int)$_ASSIGNDATA[$cellidx]["id"] && !(int)$_ASSIGNDATA[$cellidx]["assign_worker_id"] && (int)$REEMPLAZARLINEIDS[$_ASSIGNDATA[$cellidx]["id"]])
      {
         if($dayofweekidx == 7)
            $xbcss = "background-color:#EEEEEE;";
         else
            $xbcss = "background-color:#FFFFFF;";

         $tmponclick = "";
         $xbordercss = "";
         if((int)$_ASSIGNDATA_CC[$cellidx] > 1)
         {
            $xbcss = $bcss;
            $xbordercss = "border:1px dashed red;cursor:pointer;";
            $tmponclick = "onclick=\"showFancybox('/iframe.fancy.php?mid={$_REQUEST["mid"]}?>&module=workerselect&cubrirspecialslotid={$_ASSIGNDATA[$cellidx]["id"]}','iframe', 900, 600, 'auto')\"";
         }
         ?>
         <td class="content_row_os content_tbl_subheader xdelcell <?=$brdcls?>" align="center" title="<?=$cellidx?>"
         <?=$tmponclick?>
         style="<?=$xbordercss?><?=$bordercss?><?=$hidecss?>padding:2px;font-weight:normal;font-size:11px;cursor:pointer;<?=$xbcss?>">
            <?php
            if((int)$_ASSIGNDATA_CC[$cellidx] > 1)
            {
               echo "<div style=position:relative><div style='background-color:red;width:12px;height:8px;font-weight:bold;margin-left:-2px;margin-top:8px;position:absolute;font-size:8px;color:white'>".($_ASSIGNDATA_CC[$cellidx] -1)."</div></div>";
               echo $celldata;
            }
            else
            {  ?>
               &nbsp;
               <?php
            }
            ?>
         </td>
         <?php
      }
      else
      {
         if((int)$_ASSIGNDATA[$cellidx]["incidencia_cfi_id"])
         {
            $celldata = $_ASSIGNDATA[$cellidx]["inc_name_short"];

            if(date("N", $x) == 7)
               $bcss = "background-color:{$_ASSIGNDATA[$cellidx]["inc_color_dom"]} !important;";
            else
               $bcss = "background-color:{$_ASSIGNDATA[$cellidx]["inc_color"]} !important;";
            ?>
            <td class="content_row_os content_tbl_subheader xdelcell <?=$brdcls?>" align="center" title="<?=$cellidx?>"
            style="<?=$xmain?><?=$bordercss?><?=$hidecss?><?=$bcss?>padding:2px;font-weight:normal;font-size:11px;<?php
            if($_ASSIGNDATA[$cellidx]["cfi_inc_id"] == 8) echo "cursor:pointer"?>"
            <?php if($_ASSIGNDATA[$cellidx]["cfi_inc_id"] == 8)
            {  ?>
               onclick="showAssistenciaOptions(event, '<?=$_ASSIGNDATA[$cellidx]["id"]?>')"
               <?php
            }
            ?>>
               <?=$celldata?>
            </td>
            <?php
            if($_REQUEST["_MULTIMODE"])
            {
               if($_ASSIGNDATA[$cellidx]["inc_type"] == 1);
                  $_TOTALINCIDENCIAS++;
            }
         }
         else
         {
            ?>
            <td class="content_row_os content_tbl_subheader xdelcell <?=$brdcls?>" align="center" title="<?=$cellidx?>"
            <?php
            $xmultimode = $_REQUEST["_MULTIMODE"];
            if($_REQUEST["_MULTIMODE_CUBRIR"])
               $xmultimode = true;
            if((int)$_ASSIGNDATA[$cellidx]["assign_worker_id"] &&
               !(int)$_ASSIGNDATA[$cellidx]["type_libre_act"] &&
               (!$xmultimode || ($xmultimode && $cellidx == date("d.m.Y"))))
            {
               $showopt = true;
               if(!$xmultimode && $cellidx != date("d.m.Y") && $_SESSION["user_type"] != 1)
                  $showopt = false;
               if($showopt)
               {  ?>
                  onmouseover="$(this).css({'background-color':'#45E1FF'});"
                  onmouseout="$(this).css({'background-color':'<?=$basecolor?>'});"
                  onclick="showAssistenciaOptions(event, '<?=$_ASSIGNDATA[$cellidx]["id"]?>')"
                  <?php
               }
            }
            ?>
            style="<?=$xmain?><?=$bordercss?><?=$hidecss?><?=$bcss?>padding:2px;font-weight:normal;font-size:11px;cursor:pointer">
               <?=$celldata?>
               <?php
               if((int)$_ASSIGNDATA[$cellidx]["assign_worker_id"] && (int)$_ASSIGNDATA[$cellidx]["assign_isturno_extra"])
               {  ?>
                  <div style="position:relative">
                     <div style="position:absolute;bottom:-3px;right:-3px;">
                        <span style="padding:0px;line-height:8px;font-size:9px;background-color:navy;color:white;opacity:0.6">&nbsp;x&nbsp;</span>
                     </div>
                  </div>
                  <?php
               }
               if((int)$_ASSIGNDATA[$cellidx]["assign_worker_id"] && (int)$_ASSIGNDATA[$cellidx]["assign_isslot_apoyo"])
               {  ?>
                  <div style="position:relative">
                     <div style="position:absolute;bottom:7px;right:-3px;">
                        <span style="padding:0px;line-height:8px;font-size:9px;background-color:#B57600;color:white;opacity:0.6">&nbsp;a&nbsp;</span>
                     </div>
                  </div>
                  <?php
               }
               if((int)$_SESSION[$_sesmodulename]["sql_assdataact"] && (int)$_ASSIGNDATA[$cellidx]["assign_worker_id"])
               {
                  unset($_TOTALARR);
                  $_TOTALARR  = Array();
                  $wstate     = getWorkerAssistsState($_ASSIGNDATA[$cellidx], $_TOTALARR);
                  $wstate     = $wstate["_TOTALARR"];
                  $wicon      = "";
                  $wtext      = "";
                  if((int)$wstate[0]) $wicon = "";
                  elseif((int)$wstate[1]) { $wicon = "tick-circle-frame.png"; $wtext = "Asiste"; }
                  elseif((int)$wstate[3]) { $wicon = "alarm-clock-blue.png"; $wtext = "Atraso"; }
                  elseif((int)$wstate[4]) { $wicon = "megaphone.png"; $wtext = "No contesta"; }
                  elseif((int)$wstate[5]) { $wicon = "car.png"; $wtext = "En trayecto"; }
                  if($wicon != "")
                  {  ?>
                     <div style="position:relative;text-align:center">
                        <div style="width:100%;position:absolute;bottom:-2px;left:-1px;text-align:center">
                           <span style="padding:0px;line-height:8px;font-size:9px;text-align:center">
                              <img style=";text-align:center" src="/images/menu/icons/<?=$wicon?>" width="16" title="<?=$wtext?>">
                           </span>
                        </div>
                     </div>
                     <?php
                     //Guardar los iconos en session para mostrarlo en el total abajo de relevos
                     $allassidx1 = $_ASSIGNDATA[$cellidx]["assign_worker_id"];
                     $allassidx2 = $_ASSIGNDATA[$cellidx]["assign_worker_type"];
                     $allassidx3 = $cellidx;
                     $_SESSION[$_sesmodulename]["sql_allassisticons"][$allassidx1][$allassidx2][$allassidx3] = $wicon;
                  }
               }
               ?>
            </td>
            <?php
         }
      }
      if($hasCellData && $hidecss == "")
         $hideFullLine = false;
   }

   //EN ASINGACION DEN TRABAJADORES
   /*
   if($_REQUEST["mid"] == 1014 && (int)$onlyincid)
   {
      $xcounter = 0;
      $xlibrecc = 0;
      foreach($workerrow["_REEMPLAZARLINES"][$onlyincid] AS $xassrow)
      {
         if((int)$xassrow["type_libre_act"])
            $xlibrecc++;
         $xcounter++;
      }
      if($xlibrecc == $xcounter)
      {
         $hideFullLine = true;
      }
   }
   */
   
   if(($rowIsDiasPorCubrir && !$rowHasDiasPorCubrir) || $hideFullLine)
   {  ?>
      <script language="JavaScript">
         var ynodes = document.getElementsByClassName('<?=$clsname?>');
         var aNode = ynodes[0];
         aNode.style.display = 'none';
      </script>
      <?php
      //$_SESSION["JSEXEC"] .= ";$('.{$clsname}').hide(0);";
   }
   return $_ASSIGNDATA;
}

//----------------------------------------------------------------------------------
function getWorkerAssignMainRow($CON, $_ASSSEL, $idx, $selwidx = "", $allowslotsporcubrir = false)
{
   foreach(array_keys($_ASSSEL) AS $aidx)
   {
      $xarr                = explode("_", $aidx);
      $iarr                = explode("_", $idx);
      $inst_hour_id        = (int)$xarr[0];
      $workerid            = (int)$xarr[1];
      $workertype          = $xarr[2];
      $slotporcubrir       = (int)$xarr[3];

      if($allowslotsporcubrir)
         $slotporcubrir = 0;

      if($selwidx == "" || $selwidx == $workerid."_".$workertype)
      {
         if(!(int)$slotporcubrir && $xarr[0] == $iarr[0] && $xarr[1] == $iarr[1] && $xarr[2] == $iarr[2] && $xarr[3] == $iarr[3])
         {
            $_ROW["WORKERDATA"]     = getWorkerData($CON, $workerid, $workertype);
            $_ROW["ASSIGNDATA"]     = $_ASSSEL[$aidx];
         }
      }
   }

   $sql = " select t1.turn_name, t2.jorn_name
            from turnos t1
            LEFT OUTER JOIN turnos_jornadas t2 ON t1.turn_jornada_id = t2.id
            where
            t1.id = {$_ROW["ASSIGNDATA"][0]["assign_turno_id"]}";
   $turnname = $CON->select($sql);
   $_ROW["WORKERDATA"]["turn_name"] = $turnname[0]["turn_name"];
   $_ROW["WORKERDATA"]["jorn_name"] = $turnname[0]["jorn_name"];
   
   return $_ROW;
}

//----------------------------------------------------------------------------------
function getWorkerAssignSubRows($CON, $_ASSSEL, $idx, $selwidx = "", $_ASSSEL_MULTIWORKER, $_TEEXTRAMODE = false)
{
   //----------------------------------------------------------------------------------
   // ADAPTACION PARA MODULO DE PROGRAMACION
   //----------------------------------------------------------------------------------
   if($selwidx != "" && !$_TEEXTRAMODE)
   {
      $temp = $_ASSSEL_MULTIWORKER[$idx][$selwidx];
      foreach($temp AS $temprow)
      {
         if(!(int)$temprow["assign_special_slot"])
         {
            $tempidx = date("d.m.Y", $temprow["assign_stamp"]);
            $_MULTIHASDATE[$tempidx] = 1;
         }
      }
   }
   if($selwidx != "" && $_TEEXTRAMODE)
   {
      $temp = $_ASSSEL_MULTIWORKER[$idx][$selwidx];
      foreach($temp AS $temprow)
      {
         if((int)$temprow["assign_special_slot"])
         {
            $tempidx = date("d.m.Y", $temprow["assign_stamp"]);
            $_MULTIHASDATE[$tempidx] = 1;
         }
      }
   }

   //----------------------------------------------------------------------------------
   $_SUBROWS = Array();
   foreach(array_keys($_ASSSEL) AS $aidx)
   {
      $xarr                = explode("_", $aidx);
      $iarr                = explode("_", $idx);

      $inst_hour_id        = (int)$xarr[0];
      $workerid            = (int)$xarr[1];
      $workertype          = $xarr[2];
      $slotporcubrir       = (int)$xarr[3];

      if((int)$slotporcubrir && $xarr[0] == $iarr[0] && $xarr[1] == $iarr[1] && $xarr[2] == $iarr[2] && $xarr[3] == $iarr[3])
      {
         
         
         $_ROW["WORKERDATA"] = getWorkerData($CON, $workerid, $workertype);

         //----------------------------------------------------------------------------------
         // ADAPTACION PARA MODULO DE PROGRAMACION
         //----------------------------------------------------------------------------------
         if($selwidx != "")
         {
            foreach($_ASSSEL[$aidx] AS $_ASSSELROW)
            {
               $tempidx = date("d.m.Y", $_ASSSELROW["assign_stamp"]);
               if((int)$_MULTIHASDATE[$tempidx])
                 $_ROW["ASSIGNDATA"][] = $_ASSSELROW;
            }
         }
         //----------------------------------------------------------------------------------
         // AQUI NORMAL
         //----------------------------------------------------------------------------------
         else
            $_ROW["ASSIGNDATA"] = $_ASSSEL[$aidx];


         $sql = " select t1.turn_name, t2.jorn_name
                  from turnos t1
                  LEFT OUTER JOIN turnos_jornadas t2 ON t1.turn_jornada_id = t2.id
                  where
                  t1.id = {$_ROW["ASSIGNDATA"][0]["assign_turno_id"]}";
         $turnname = $CON->select($sql);
         $_ROW["WORKERDATA"]["turn_name"] = $turnname[0]["turn_name"];
         $_ROW["WORKERDATA"]["jorn_name"] = $turnname[0]["jorn_name"];
         $_SUBROWS[] = $_ROW;
      }
   }

   //----------------------------------------------------------------------------------
   // ADAPTACION PARA MODULO DE PROGRAMACION
   //----------------------------------------------------------------------------------
   if($selwidx != "")
   {
      $temp = $_SUBROWS;
      unset($_SUBROWS);
      $_SUBROWS = Array();

      foreach($temp AS $temprow)
      {
         $wdata = $temprow["WORKERDATA"];
         $adata = $temprow["ASSIGNDATA"];
         $aarr  = Array();

         //----------------------------------------------------------------------------------
         if((int)$wdata["id"])
         {
            foreach($adata AS $adatarow)
            {
               if((int)$adatarow["assign_worker_id"] == (int)$wdata["id"])
                  $aarr[] = $adatarow;
            }
         }

         //----------------------------------------------------------------------------------
         if(!(int)$wdata["id"])
         {
            foreach($adata AS $adatarow)
            {
               if(!(int)$adatarow["assign_worker_id"])
                  $aarr[] = $adatarow;
            }
         }

         $_NEWROW["WORKERDATA"] = $wdata;
         $_NEWROW["ASSIGNDATA"] = $aarr;
         $_SUBROWS[] = $_NEWROW;
      }
   }

   return $_SUBROWS;
}

//----------------------------------------------------------------------------------
function getWorkerData($CON, $workerid, $workertype)
{
   if($workertype == "worker")
   {
      $sql = " select distinct t1.id, t1.wrk_firstname, t1.wrk_lastname, 
                      t1.wrk_telefono1, t1.wrk_telefono2, t1.wrk_telefono3, t1.wrk_email,
                      t1.wrk_rut, t1.wrk_foto, t1.wrk_titulo, t2.type_name, 'worker' AS 'type'
               from workers t1
               LEFT OUTER JOIN workers_types t2 ON t1.wrk_cargoid = t2.id
               where
               t1.id = {$workerid}";
      $workerdata = $CON->select($sql);
      $workerdata = $workerdata[0];
   }
   elseif($workertype == "supervisor")
   {
      $sql = " select t1.id, t1.user_firstname 'wrk_firstname', t1.user_lastname 'wrk_lastname', 
               t1.user_telephone 'wrk_telefono1', t1.user_cellphone 'wrk_telefono2',
               t1.user_mail 'wrk_email', t1.user_rut 'wrk_rut', t1.user_pic 'wrk_foto',
               t1.user_titulo 'wrk_titulo', t2.type_name, 'supervisor' AS 'type'
               from user t1
               LEFT OUTER JOIN workers_types t2 ON t1.user_cargoid = t2.id
               where
               t1.id = {$workerid}";
      $workerdata = $CON->select($sql);
      $workerdata = $workerdata[0];
   }
   return $workerdata;
}

//----------------------------------------------------------------------------------
function getWorkerOverlib($workerdata, $instname, $instid)
{
   global $CON;
   
   $workerdata["wrk_lastname"]   = str_replace("\r", "", str_replace("\n", "", str_replace("'", "", str_replace("\"", "", $workerdata["wrk_lastname"]))));
   $workerdata["wrk_firstname"]  = str_replace("\r", "", str_replace("\n", "", str_replace("'", "", str_replace("\"", "", $workerdata["wrk_firstname"]))));
   $workerdata["wrk_telefono1"]  = str_replace("\r", "", str_replace("\n", "", str_replace("'", "", str_replace("\"", "", $workerdata["wrk_telefono1"]))));
   $workerdata["wrk_telefono2"]  = str_replace("\r", "", str_replace("\n", "", str_replace("'", "", str_replace("\"", "", $workerdata["wrk_telefono2"]))));
   $workerdata["wrk_telefono3"]  = str_replace("\r", "", str_replace("\n", "", str_replace("'", "", str_replace("\"", "", $workerdata["wrk_telefono3"]))));
   $workerdata["wrk_email"]      = str_replace("\r", "", str_replace("\n", "", str_replace("'", "", str_replace("\"", "", $workerdata["wrk_email"]))));
   $workerdata["wrk_titulo"]     = str_replace("\r", "", str_replace("\n", "", str_replace("'", "", str_replace("\"", "", $workerdata["wrk_titulo"]))));
   $workerdata["type_name"]      = str_replace("\r", "", str_replace("\n", "", str_replace("'", "", str_replace("\"", "", $workerdata["type_name"]))));

   $xtype = "Supervisor";
   if($workerdata["type"] == "worker")
      $xtype = "Trabajador";
   $overlibover  = "<table bgcolor=#FFFFFF class=content_table border=0 cellpadding=2 cellspacing=0 width=450>";
   $overlibover .= "<colgroup><col width=100><col></colgroup>";
   $overlibover .= "<tr>";
   $overlibover .= "<td class=content_tbl_header colspan=2><b>Datos de Contacto</b></td>";
   $overlibover .= "</tr>";
   $overlibover .= "<tr>";
   $overlibover .= "<td class=content_rowl style=\'border-top:2px solid #666666\'>Trabajador</td>";
   $overlibover .= "<td class=content_row style=\'border-top:2px solid #666666\'>{$workerdata["wrk_lastname"]}, {$workerdata["wrk_firstname"]}</td>";
   $overlibover .= "</tr>";
   $overlibover .= "<tr>";
   $overlibover .= "<td class=content_rowl>Tipo</td>";
   $overlibover .= "<td class=content_row>{$xtype}</td>";
   $overlibover .= "</tr>";
   $telidx = 1;
   if($workerdata["wrk_titulo"] != "")
   {
      $overlibover .= "<tr>";
      $overlibover .= "<td class=content_rowl>Titulo</td>";
      $overlibover .= "<td class=content_row>{$workerdata["wrk_titulo"]}</td>";
      $overlibover .= "</tr>";
      $telidx++;
   }
   if($workerdata["type_name"] != "")
   {
      $overlibover .= "<tr>";
      $overlibover .= "<td class=content_rowl>Cargo</td>";
      $overlibover .= "<td class=content_row>{$workerdata["type_name"]}</td>";
      $overlibover .= "</tr>";
      $telidx++;
   }
   if($workerdata["wrk_telefono1"] != "")
   {
      $overlibover .= "<tr>";
      $overlibover .= "<td class=content_rowl>Tel�fono</td>";
      $overlibover .= "<td class=content_row>{$workerdata["wrk_telefono1"]}</td>";
      $overlibover .= "</tr>";
      $telidx++;
   }
   if($workerdata["wrk_telefono2"] != "")
   {
      $overlibover .= "<tr>";
      $overlibover .= "<td class=content_rowl>Tel�fono</td>";
      $overlibover .= "<td class=content_row>{$workerdata["wrk_telefono2"]}</td>";
      $overlibover .= "</tr>";
      $telidx++;
   }
   if($workerdata["wrk_telefono3"] != "")
   {
      $overlibover .= "<tr>";
      $overlibover .= "<td class=content_rowl>Tel�fono</td>";
      $overlibover .= "<td class=content_row>{$workerdata["wrk_telefono3"]}</td>";
      $overlibover .= "</tr>";
      $telidx++;
   }
   if($workerdata["wrk_foto"] != "")
   {
      if($workerdata["type"] == "worker")
      {
         $overlibover .= "<tr>";
         $overlibover .= "<td class=content_rowl valign=top>Foto</td>";
         $overlibover .= "<td class=content_row><img src=/images/worker_images/{$workerdata["wrk_foto"]} height=120px></td>";
         $overlibover .= "</tr>";
         $telidx++;
      }
      else
      {
         $overlibover .= "<tr>";
         $overlibover .= "<td class=content_rowl valign=top>Foto</td>";
         $overlibover .= "<td class=content_row><img src=/images/user_pics/{$workerdata["wrk_foto"]} height=120px></td>";
         $overlibover .= "</tr>";
         $telidx++;
      }
   }
   $overlibover .= "</table>";

   $overmouse     = "RIGHT";
   $overlibover   = "return overlib('{$overlibover}', WIDTH, 450, {$overmouse}, FGCOLOR, '#FFFFFF', BGCOLOR, '#333333', ABOVE)";
   $overlibout    = "return nd()";

   return "onmouseover=\"{$overlibover}\" onmouseout=\"{$overlibout}\"";
}

function pdfConvertStyles($str)
{
   if(strpos($str, "<b><u><i>") !== false ||
      strpos($str, "<b><i><u>") !== false ||
      strpos($str, "<u><b><i>") !== false ||
      strpos($str, "<u><i><b>") !== false ||
      strpos($str, "<i><b><u>") !== false ||
      strpos($str, "<i><u><b>") !== false)
   {
      $str = str_replace("<b><u><i>",      '<font size="10" style="bold" family="Helvetica">######XBUI#####', $str);
      $str = str_replace("</b></u></i>",   '######XFBUI#####</font>', $str);
      $str = str_replace("<b><i><u>",      '<font size="10" style="bold" family="Helvetica">######XBUI#####', $str);
      $str = str_replace("</b></i></u>",   '######XFBUI#####</font>', $str);
      $str = str_replace("<u><b><i>",      '<font size="10" style="bold" family="Helvetica">######XBUI#####', $str);
      $str = str_replace("</u></b></i>",   '######XFBUI#####</font>', $str);
      $str = str_replace("<u><i><b>",      '<font size="10" style="bold" family="Helvetica">######XBUI#####', $str);
      $str = str_replace("</u></i></b>",   '######XFBUI#####</font>', $str);
      $str = str_replace("<i><b><u>",      '<font size="10" style="bold" family="Helvetica">######XBUI#####', $str);
      $str = str_replace("</i></b></u>",   '######XFBUI#####</font>', $str);
      $str = str_replace("<i><u><b>",      '<font size="10" style="bold" family="Helvetica">######XBUI#####', $str);
      $str = str_replace("</i></u></b>",   '######XFBUI#####</font>', $str);
   }
   if(strpos($str, "<b><u>") !== false || strpos($str, "<u><b>") !== false)
   {
      $str = str_replace("<b><u>", '<font size="10" style="underline" family="Helvetica">######XBU#####', $str);
      $str = str_replace("</b></u>", '######XFBU#####</font>', $str);
      $str = str_replace("<u><b>", '<font size="10" style="underline" family="Helvetica">######XBU#####', $str);
      $str = str_replace("</u></b>", '######XFBU#####</font>', $str);
   }
   if(strpos($str, "<b><i>") !== false || strpos($str, "<i><b>") !== false)
   {
      $str = str_replace("<b><i>", '<font size="10" style="italic" family="Helvetica">######XBI#####', $str);
      $str = str_replace("</b></i>", '######XFBI#####</font>', $str);
      $str = str_replace("<i><b>", '<font size="10" style="italic" family="Helvetica">######XBI#####', $str);
      $str = str_replace("</i></b>", '######XFBI#####</font>', $str);
   }
   if(strpos($str, "<u><i>") !== false || strpos($str, "<i><u>") !== false)
   {
      $str = str_replace("<u><i>", '<font size="10" style="underline" family="Helvetica">######XUI#####', $str);
      $str = str_replace("</u></i>", '######XFUI#####</font>', $str);
      $str = str_replace("<i><u>", '<font size="10" style="underline" family="Helvetica">######XUI#####', $str);
      $str = str_replace("</i></u>", '######XFUI#####</font>', $str);
   }
   if(strpos($str, "<b>") !== false)
   {
      $str = str_replace("<b>", '<font size="10" style="bold" family="Helvetica">######IB#####', $str);
      $str = str_replace("</b>", '######FB#####</font>', $str);
   }
   if(strpos($str, "<u>") !== false)
   {
      $str = str_replace("<u>", '<font size="10" style="underline" family="Helvetica">######IU#####', $str);
      $str = str_replace("</u>", '######FU#####</font>', $str);
   }
   if(strpos($str, "<i>") !== false)
   {
      $str = str_replace("<i>", '<font size="10" style="italic" family="Helvetica">######II#####', $str);
      $str = str_replace("</i>", '######FI#####</font>', $str);
   }
   return $str;
}

//----------------------------------------------------------------------------------
function getSuppOrderCharacts()
{
   $idx = 0;
   $_RET[$idx]["id"]    = 1;
   $_RET[$idx]["name"]  = "Type of bags";
   $_RET[$idx]["small"] = true;
   $idx++;
   $_RET[$idx]["id"]    = 2;
   $_RET[$idx]["name"]  = "Material";
   $_RET[$idx]["small"] = true;
   $idx++;
   $_RET[$idx]["id"]    = 3;
   $_RET[$idx]["name"]  = "Color";
   $_RET[$idx]["small"] = true;
   $idx++;
   $_RET[$idx]["id"]    = 4;
   $_RET[$idx]["name"]  = "Bag Size";
   $_RET[$idx]["small"] = true;
   $idx++;
   $_RET[$idx]["id"]    = 5;
   $_RET[$idx]["name"]  = "Handle";
   $_RET[$idx]["small"] = true;
   $idx++;
   $_RET[$idx]["id"]    = 6;
   $_RET[$idx]["name"]  = "Packing";
   $_RET[$idx]["small"] = true;
   $idx++;
   $_RET[$idx]["id"]    = 7;
   $_RET[$idx]["name"]  = "Printing";
   $idx++;
   $_RET[$idx]["id"]    = 10;
   $_RET[$idx]["name"]  = "Print cylinder";
   $idx++;
   $_RET[$idx]["id"]    = 8;
   $_RET[$idx]["name"]  = "Printing Referential Color";
   $idx++;
   $_RET[$idx]["id"]    = 9;
   $_RET[$idx]["name"]  = "Special requirements";
   $idx++;

   return $_RET;
}

//----------------------------------------------------------------------------------
function getTiposEquipo($CON)
{
   $sql = " select t1.*
            from equipo_type t1
            where
            t1.type_ant_status = 1
            order by t1.type_ant_title ";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getValeData($invcdata)
{
   $_RET["invcdesc"]   = explode(",", $invcdata["invc_desc"]);
   $_RET["factcount"]  = trim(str_replace("FACTURA ", "", $_RET["invcdesc"][0]));
   $_RET["valenum"]    = trim(str_replace("VALE: ", "", $_RET["invcdesc"][1]));
   $_RET["custid"]     = $invcdata["invc_cust_id"];
   $_RET["shopid"]     = $invcdata["invc_shop_id"];

   return $_RET;
}

//----------------------------------------------------------------------------------
function getTypeDocContList($CON, $type = "")
{
   $sql = " select *
            from typedoc_contables
            where
            typedoc_cont_status > 0";
   if($type == "FACTURA")
      $sql .= " and typedoc_cont_nameid IN (1,3) ";
   elseif($type == "FACTURAEXT")
      $sql .= " and typedoc_cont_nameid IN (2,4) ";
   elseif($type == "NOTACRED")
      $sql .= " and typedoc_cont_nameid IN (5,7) ";
   elseif($type == "NOTADEB")
      $sql .= " and typedoc_cont_nameid IN (6,8) ";
   else
      $sql .= " 1 = 2 ";
   $sql .= " order by typedoc_cont_nameid, typedoc_cont_code";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getTypeDocContName($typeid)
{
   switch((int)$typeid)
   {
      case 1: return "Factura (manual)"; break;
      case 2: return "Factura exenta"; break;
      case 3: return "Factura (electr.)"; break;
      case 4: return "Factura exenta (electr.)"; break;
      case 5: return "Nota de credito (manual)"; break;
      case 6: return "Nota de debito (manual)"; break;
      case 7: return "Nota de credito (electr.)"; break;
      case 8: return "Nota de debito (electr.)"; break;
      case 9: return "Factura mixta"; break;
      default: return "&nbsp;"; break;
   }
}

//----------------------------------------------------------------------------------
function getOtherSuppliers($CON)
{
   $sql = " select t1.*, t2.country_name, t3.name as 'region_name', t4.nombre as 'comuna_name' 
            from adm_supplier t1
            LEFT OUTER JOIN  country t2 on t2.id = t1.supp_countryid
            LEFT OUTER JOIN  regions t3 on t3.id = t1.supp_regionid
            LEFT OUTER JOIN  comunas t4 on t4.id = t1.supp_comunaid
            where
            t1.supp_status = 1
            order by t1.supp_company ASC";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getAdmAccounts($CON)
{
   $sql = " select *
            from adm_accounts
            where
            acc_status > 0
            order by acc_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function get_number_ranges($numbers)
{
    $last = null;
    foreach ($numbers as $number) {
        if (is_null($last)) {
            $string = $number;
            $last = $number;
            $first = $number ;                          //Remember first
        } elseif ($last + 1 != $number) {
            if ($first != $last )                       //Only append if different
                $string .= '-' . $last ;
            $string .= ', ' . $number;
            $last = $number;
            $first = $number ;                          //Remember first
        } else {
            $last = $number;
        }
    }

    if ($last == $number && $last != $first) {          //Only if different
        $string .= '-' . $number;
    }

    return $string;
}

//----------------------------------------------------------------------------------
function addSyncTransaction($CON, $sync_module, $sync_refid, $sync_companyid, $sync_shopid, $showMessage = true, $sync_plandate = 0)
{
   /*
   $currtme = time();
   $sync_plandate = (int)$sync_plandate;

   $sql = " insert into company_shops_sync
            (sync_module, sync_refid, sync_companyid, sync_shopid, sync_plandate, sync_crtdat, sync_crtusr)
            VALUES
            ('{$sync_module}', {$sync_refid}, {$sync_companyid}, {$sync_shopid},
              {$sync_plandate}, {$currtme}, {$_SESSION["user_id"]})";
   $res = $CON->no_result($sql);
   if($showMessage)
   {
      if($res)
      {  ?>
         <script language="JavaScript">
            alert('LOS DATOS SE AGREGARON A LA COLA DE SINCRONIZACION.');
         </script>
         <?php
      }
      else
      {  ?>
         <script language="JavaScript">
            alert('ERROR: NO SE PUEDEN AGREGAR LOS DATOS A LA SINCRONIZACION.');
         </script>
         <?php
      }
   }
   */
}

//----------------------------------------------------------------------------------
function getSyncTransactionDesc($CON, $id)
{
   $sql = " select *
            from company_shops_sync
            where
            id = {$id}";
   $data = $CON->select($sql);
   $data = $data[0];

   switch($data["sync_module"])
   {
      case "customer_types":
         $sql = " select t2.cust_name
                  from customer_types t1
                  INNER JOIN customer t2 ON t1.ct_cust_id = t2.id
                  where
                  t1.id = {$data["sync_refid"]}";
         $customer = $CON->select($sql);
         return "<br>".$customer[0]["cust_name"];
   }
   return "";
}


//----------------------------------------------------------------------------------
function getProvincias($CON)
{
   $sql = " select *
            from provincias
            where
            pro_status > 0
            order by pro_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getInvoiceSellNoteConType($idx)
{
   switch((int)$idx)
   {
      case 0: return "Factura Electr."; break;
      case 1: return "Nota de Credito Electr."; break;
      case 2: return "Nota de Debito Electr."; break;
      case 3: return "Ningun"; break;
      case 4: return "Factura"; break;
      case 5: return "Nota de Credito"; break;
      case 6: return "Nota de Debito"; break;
      case 7: return "Boleta Fiscal"; break;
      case 8: return "Boleta Electronica"; break;
      default:  return "Desconocido";
   }
}

//----------------------------------------------------------------------------------
function fieldNavNextFields($_FIELDREGS, $_FIELDIGNORES, $x, $y)
{
   $jqrowfields = array_values($_FIELDREGS[$x]);
   $jqmaxfields = count($jqrowfields);

   $_RES["UP"]    = $_FIELDREGS[($x -1)][$y];
   $_RES["DOWN"]  = $_FIELDREGS[($x +1)][$y];
   $_RES["RIGHT"] = $_FIELDREGS[$x][($y +1)];
   $_RES["LEFT"]  = $_FIELDREGS[$x][($y -1)];
   
   if((int)$_FIELDIGNORES[$_RES["UP"]])      $_RES["UP"] = "";
   if((int)$_FIELDIGNORES[$_RES["DOWN"]])    $_RES["DOWN"] = "";
   if((int)$_FIELDIGNORES[$_RES["RIGHT"]])   $_RES["RIGHT"] = "";
   if((int)$_FIELDIGNORES[$_RES["LEFT"]])    $_RES["LEFT"] = "";
      
   if($_RES["UP"] == "")
   {
      $found = false;
      for($z = $y; $z < $jqmaxfields && !$found; $z++)
      {
         $fname = $_FIELDREGS[($x -1)][$z];
         if($fname != "" && !(int)$_FIELDIGNORES[$fname])
         {
            $found = true;
            $_RES["UP"] = $fname;
         }
      }
      if(!$found)
      {
         for($z = $y; $z >= 0 && !$found; $z--)
         {
            $fname = $_FIELDREGS[($x -1)][$z];
            if($fname != "" && !(int)$_FIELDIGNORES[$fname])
            {
               $found = true;
               $_RES["UP"] = $fname;
            }
         }
      }
   }
   if($_RES["DOWN"] == "")
   {
      $found = false;
      for($z = $y; $z < $jqmaxfields && !$found; $z++)
      {
         $fname = $_FIELDREGS[($x +1)][$z];
         if($fname != "" && !(int)$_FIELDIGNORES[$fname])
         {
            $found = true;
            $_RES["DOWN"] = $fname;
         }
      }
      if(!$found)
      {
         for($z = $y; $z >= 0 && !$found; $z--)
         {
            $fname = $_FIELDREGS[($x +1)][$z];
            if($fname != "" && !(int)$_FIELDIGNORES[$fname])
            {
               $found = true;
               $_RES["DOWN"] = $fname;
            }
         }
      }
   }
   if($_RES["RIGHT"] == "")
   {
      $found = false;
      for($z = $y +2; $z < $jqmaxfields && !$found; $z++)
      {
         $fname = $_FIELDREGS[$x][$z];
         if($fname != "" && !(int)$_FIELDIGNORES[$fname])
         {
            $found = true;
            $_RES["RIGHT"] = $fname;
         }
      }
      if(!$found)
      {
         for($z = 0; $z < $jqmaxfields && !$found; $z++)
         {
            $fname = $_FIELDREGS[($x +1)][$z];
            if($fname != "" && !(int)$_FIELDIGNORES[$fname])
            {
               $found = true;
               $_RES["RIGHT"] = $fname;
            }
         }
      }
   }
   if($_RES["LEFT"] == "")
   {
      $found = false;
      for($z = $y -2; $z >= 0 && !$found; $z--)
      {
         $fname = $_FIELDREGS[$x][$z];
         if($fname != "" && !(int)$_FIELDIGNORES[$fname])
         {
            $found = true;
            $_RES["LEFT"] = $fname;
         }
      }
      if(!$found)
      {
         for($z = $jqmaxfields -1; $z >= 0 && !$found; $z--)
         {
            $fname = $_FIELDREGS[($x -1)][$z];
            if($fname != "" && !(int)$_FIELDIGNORES[$fname])
            {
               $found = true;
               $_RES["LEFT"] = $fname;
            }
         }
      }
   }

   return $_RES;
}

//----------------------------------------------------------------------------------
function printGooglemapsButton($street, $region, $country)
{
   $gmapstr = "";
   if(trim($street) != "")
      $gmapstr .= "{$street}, ";
   if(trim($region) != "")
      $gmapstr .= "{$region}, ";
   if(trim($country) != "")
      $gmapstr .= "{$country}, ";
   $gmapstr = substr($gmapstr, 0, -2);

   $url = "http://maps.google.com/maps?f=q&hl=es&q=".urlencode(htmlentities($gmapstr))."&t=h&iwloc=near&output=embed&z=15";

   printButton("", "postnav", "javascript:void(0)", "showFancybox('{$url}', 'iframe', 800, 450, 'auto')", "globe-model", 30);
}

//----------------------------------------------------------------------------------
function generateCommentToogle($notes)
{
   $notes = trim($notes);
   $notes = str_replace("\r", "\n", $notes);
   $notes = str_replace("\n\n", "\n", $notes);
   $notes = str_replace("\n\n", "\n", $notes);
   $notes = str_replace("\n\n", "\n", $notes);
   $notes = str_replace("\n\n", "\n", $notes);

   if(strlen($notes) > 100)
   {
      $notes_short = substr($notes, 0, 99)."...";
      $expand_hash = md5(microtime());
      ?>
      <span class="expand_<?=$expand_hash?>" style="margin:0px; padding:0px;text-decoration:none"><img
      border="0" src="./images/menu/icons/exclamation-button.png" style="vertical-align:bottom;padding-right:3px"><?=$notes_short?></span>
      <div class="collapse" style="margin:0px; padding-top:10px"><?=nl2br($notes)?></div>
      <script type="text/javascript">
         $(function() { $("span.expand_<?=$expand_hash?>").toggler({method: "fadeToggle"}); });
      </script>
      <?php
   }
   else
   {  ?>
      <div style="margin:0px; padding-top:0px;color:red"><img
      border="0" src="./images/menu/icons/exclamation-button.png" style="vertical-align:bottom;padding-right:3px"><?=nl2br($notes)?></div>
      <?php
   }
}

//----------------------------------------------------------------------------------
function printBottomBarDiv()
{
   global $_LANG;

   if((int)$_SESSION["user_sidepanel_active"])
   {
      //----------------------------------------------------------------------------------
      $parentitem = $_SESSION["_MENU"]->m_rawstruct[$_REQUEST["mid"]]["menu_parent"];
      if($parentitem)
         $panelitems = $_SESSION["_MENU"]->m_rawstruct[$parentitem]["items"];

      ?>
      <style>
      div.obitpanel
      {
         <?php
         if((int)$_SESSION["jschk_obitpanel"])
            echo "width: 235px;";
         else
            echo "width: 40px;";
         ?>
         position: fixed;
         top: 30px;
         right:0px;
         z-index: 100000;
         margin:0px;
         padding:0px;
         background-color:#888888;
          filter: alpha(opacity=87);
          -moz-opacity:0.87;
          opacity:0.87;
      }
      </style>
      <script language="JavaScript">
         //----------------------------------------------------------------------------------
         function obitPanelShow()
         {
            if (navigator.appVersion.indexOf('MSIE') != -1)
               document.getElementById('obitpanel').style.width = '215px';
            else
               $('#obitpanel').stop().animate({'width':'235px'},100);
         }
         //----------------------------------------------------------------------------------
         function obitPanelShowFast()
         {
            if (navigator.appVersion.indexOf('MSIE') != -1)
               document.getElementById('obitpanel').style.width = '215px';
            else
            {
               document.getElementById('obitpanel').style.width = '235px';
            }
         }
         //----------------------------------------------------------------------------------
         function obitPanelHide()
         {
            document.getElementById('obitpanel').style.display='';
            if(document.getElementById('jschk_obitpanel').value == '1')
               obitPanelShowFast();
            else
            {
               if (navigator.appVersion.indexOf('MSIE') != -1)
                  document.getElementById('obitpanel').style.width = '20px';
               else
                  $('#obitpanel').stop().animate({'width':'40px'},100);
            }
         }
      </script>
      <div class="obitpanel" id="obitpanel" style="display:none"
      onmouseenter="if (navigator.appVersion.indexOf('MSIE') != -1) obitPanelShow()"
      onmouseover="if (navigator.appVersion.indexOf('MSIE') == -1) obitPanelShow()"
      onmouseleave="if (navigator.appVersion.indexOf('MSIE') != -1) obitPanelHide()"
      onmouseout="if (navigator.appVersion.indexOf('MSIE') == -1) obitPanelHide()">
         <table border="0" cellpadding="3" cellspacing="0" width="99%" class="no-print" style="table-layout:fixed;padding:7px">
         <colgroup>
            <col width="30">
            <col>
         </colgroup>
         <?php
         $btndel = false;
         $favs = $_SESSION["_MENU"]->getFavorites();
         foreach(array_keys($favs) AS $favkey)
         {
            $cssfav = "";
            if($_REQUEST["mid"] == $favkey)
            {
               $btndel = true;
               $cssfav = "<b>";

               $favicon = "tick-button";
            }
            else
               $favicon = "shortcut";
            ?>
            <tr>
               <td align="center" valign="top"><img src="/images/menu/icons/<?=$favicon?>.png"></td>
               <td valign="top" class="page_subheader">
                  <nobr><font onclick="location.href='<?=$favs[$favkey]["URL"]?>'" style="cursor:pointer"
                  onmouseover="this.style.textDecoration='underline'"
                  onmouseout="this.style.textDecoration='none'"><?=$cssfav?><?=$favs[$favkey]["NAME"]?></font></nobr><br>
               </td>
            </tr>
            <?php
         }
         foreach($panelitems AS $panelitem)
         {
            //----------------------------------------------------------------------------------
            if($panelitem["menu_icon"] != "")
               $entry_icn = str_replace(".png", "", $panelitem["menu_icon"]);
            else
               $entry_icn = "question-frame";

            //----------------------------------------------------------------------------------
            $xurl = "";
            if($panelitem["menu_link"] == "1" || $panelitem["menu_link"] == "2")
            {
               //----------------------------------------------------------------------------------
               if($panelitem["menu_link"] == "1")
               {
                  $xurl = "index.php?mid={$panelitem["id"]}";

                  if($panelitem["menu_mod_params"] != "")
                     $xurl .= "&{$panelitem["menu_mod_params"]}";
               }
               else
                  $xurl   = "index.php?mid={$panelitem["id"]}&doc_id={$panelitem["menu_docid"]}&type={$panelitem["menu_behavior"]}";

               //----------------------------------------------------------------------------------
               ?>
               <tr>
                  <td align="center" valign="top"><img src="/images/menu/icons/<?=$entry_icn?>.png"></td>
                  <td valign="top" class="page_subheader">
                     <nobr><font onclick="location.href='<?=$xurl?>'" style="cursor:pointer"
                     onmouseover="this.style.textDecoration='underline'"
                     onmouseout="this.style.textDecoration='none'"><?=$panelitem["menu_name"]?></font></nobr><br>
                  </td>
               </tr>
               <?php
            }
         }

         ?>
         <tr>
            <?php
            if($btndel)
            {  ?>
               <td align="center"><img src="/images/menu/icons/minus-circle-frame.png"></td>
               <td>
                  <?php
                  printButton("Borrar de marcadores", "postnav_del", "javascript: void(0)", "askDel('index.php?delfav={$_REQUEST["mid"]}')", "", 180);
                  ?>
               </td>
               <?php
            }
            elseif($_REQUEST["mid"] != 99999)
            {  ?>
               <td align="center"><img src="/images/menu/icons/plus-circle-frame.png"></td>
               <td>
                  <?php
                  printButton("Agregar a marcadores", "postnav_save", "index.php?setfav={$_REQUEST["mid"]}", "", "", 180);
                  ?>
               </td>
               <?php
            }
            ?>
         </tr>
         <tr>
            <td align="center"><input type="checkbox" class="checkbox"
            <?php if((int)$_SESSION["jschk_obitpanel"]) echo "checked"?>
            onclick="if(this.checked)
                     {
                        document.getElementById('jschk_obitpanel').value = '1';
                        document.getElementById('idx_iframe_setpanel').src='index.php?mid=99999&setObitPanel=1';
                     }
                     else
                     {
                        document.getElementById('jschk_obitpanel').value = '0';
                        document.getElementById('idx_iframe_setpanel').src='index.php?mid=99999&setObitPanel=0';
                     }"></td>
            <td class="page_subheader"><nobr>Siempre encima</nobr></td>
         </tr>
         </table>
      </div>
      <?php
      $_SESSION["JSEXEC"] .= ";obitPanelHide();";
   }
   else
   {  ?>
      <div class="obitpanel" id="obitpanel" style="display:none"></div>
      <?php
   }
}

//----------------------------------------------------------------------------------
function resetOverviewSession($_sesmodulename)
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["searchexec"] == "reset")
   {
      $fstatus = $_SESSION[$_sesmodulename]["filter_status"];
      $forder  = $_SESSION[$_sesmodulename]["orderBy"];
      $fsort   = $_SESSION[$_sesmodulename]["orderSort"];

      unset($_SESSION[$_sesmodulename]);
      
      $_SESSION[$_sesmodulename]["filter_status"]  = $fstatus;
      $_SESSION[$_sesmodulename]["orderBy"]        = $forder;
      $_SESSION[$_sesmodulename]["orderSort"]      = $fsort;
      $_SESSION[$_sesmodulename]["sql_company"]    = $_SESSION["user_company_id"];
      
      $_REQUEST["subexec"] = "";
   }
}

//----------------------------------------------------------------------------------
function prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, $linecount = 75)
{
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["orderBy"] == "")
      $_SESSION[$_sesmodulename]["orderBy"] = $_sesbaseorderby;
   if($_SESSION[$_sesmodulename]["orderSort"] == "")
      $_SESSION[$_sesmodulename]["orderSort"] = $_sesbaseordersort;
      
   //----------------------------------------------------------------------------------
   if($_REQUEST["orderBy"] != "")
   {
      $_REQUEST["orderBy"] = str_replace(" ", "", $_REQUEST["orderBy"]);
      if(str_replace(" desc", "", str_replace(" asc", "", $_SESSION[$_sesmodulename]["orderBy"])) == $_REQUEST["orderBy"])
      {
         if($_SESSION[$_sesmodulename]["orderSort"] == "asc")
            $_SESSION[$_sesmodulename]["orderSort"] = "desc";
         else
            $_SESSION[$_sesmodulename]["orderSort"] = "asc";
      }
      else
         $_SESSION[$_sesmodulename]["orderSort"] = "desc";

      $_SESSION[$_sesmodulename]["orderBy"] = $_REQUEST["orderBy"];

      if(strpos($_SESSION[$_sesmodulename]["orderBy"],",") !== false)
         $_SESSION[$_sesmodulename]["orderBy"] = str_replace(",", " {$_SESSION[$_sesmodulename]["orderSort"]},", $_SESSION[$_sesmodulename]["orderBy"]);

      if($_REQUEST["orderSort"] != "")
         $_SESSION[$_sesmodulename]["orderSort"] = $_REQUEST["orderSort"];
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["page"] != "")
      $_SESSION[$_sesmodulename]["page"] = $_REQUEST["page"];

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["rows_per_page"] == "")
   {
      $_SESSION[$_sesmodulename]["rows_per_page"]   = $linecount;
      $_SESSION[$_sesmodulename]["page"]            = 0;
      $_SESSION[$_sesmodulename]["startrow"]        = 0;
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["setstatus"] != "")
      $_SESSION[$_sesmodulename]["filter_status"] = $_REQUEST["setstatus"];
   if($_SESSION[$_sesmodulename]["filter_status"] == "")
      $_SESSION[$_sesmodulename]["filter_status"] = $_sesbasefilterstatus;
}

//----------------------------------------------------------------------------------
function getCompanies($CON, $all = false, $id = 0)
{
   $sql = " select t1.*, t2.name 'region', t3.nombre 'comuna'
            from company_data t1
            LEFT OUTER JOIN regions t2 ON t1.company_regionid = t2.id
            LEFT OUTER JOIN comunas t3 ON t1.company_comunaid = t3.id
            where
            t1.company_status = 1 ";
   if(!$all)
      $sql .= "and t1.id = {$_SESSION["user_company_id"]} ";
   if((int)$id)
      $sql .= "and t1.id = {$id} ";
   $sql .= " order by t1.company_short";
   return $CON->select($sql);
   
}

//----------------------------------------------------------------------------------
function getShops($CON, $onlyremote = false, $onlycentral = false, $compid = 0, $shopid = 0)
{
   $sql = " select t1.*, t2.company_short, t2x.name 'region', t3x.nombre 'comuna'
            from company_shops t1
            INNER JOIN company_data t2  ON t1.shop_company_id = t2.id
            LEFT OUTER JOIN regions t2x ON t1.shop_regionid = t2x.id
            LEFT OUTER JOIN comunas t3x ON t1.shop_comunaid = t3x.id
            where
            t1.shop_status    = 1 and
            t2.company_status = 1 ";
   if($onlyremote)
      $sql .= " and t1.shop_isremote = 1 ";
   if($onlycentral)
      $sql .= " and t1.shop_isremote = 0 ";
   if((int)$compid)
      $sql .= " and t1.shop_company_id = {$compid} ";
   if((int)$shopid)
      $sql .= " and t1.id = {$shopid} ";
   $sql .= " order by t2.company_short, t1.shop_name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getSuppliers($CON)
{
   $sql = " select t1.*
            from supplier t1
            where
            t1.supp_status = 1
            order by t1.supp_company";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getCustomers($CON)
{
   $sql = " select *
            from customer
            where
            cust_status = 1
            order by cust_name";
   $customers = $CON->select($sql);
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getCountries($CON)
{
   $sql = " select id, country_name
            from country
            where
            country_status = 1
            order by country_name asc";
   return $CON->select($sql);
}

function getTablas($CON)
{
   $sql = " select distinct tabla
            from parametros
            order by tabla";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getRegions($CON)
{
   $sql = " select *
            from regions
            where
            estado = 1
            order by name";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getComunas($CON)
{
   $sql = " select *
            from comunas
            where
            estado > 0
            order by nombre";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getAllComunas($CON, $comunaid = 0)
{
   $sql = " select distinct t1.*, t1.id_region, t1.prov_id, t2.id_pais
            from comunas t1
            INNER JOIN regions t2      ON t1.id_region = t2.id
            INNER JOIN provincias t3   ON t1.prov_id = t3.id
            INNER JOIN country t4      ON t2.id_pais = t4.id
            where
            t1.estado         > 0 and
            t2.estado         > 0 and
            t3.pro_status     > 0 and
            t4.country_status > 0 ";
   if((int)$comunaid)
      $sql .= " and t1.id = {$comunaid} ";
   $sql .= " order by t1.nombre";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getPayments($CON, $shopid = 0)
{
   if((int)$shopid)
   {
      $sql = " select t1.*
               from payments t1
               INNER JOIN payments_shops t2 ON t1.id = t2.pay_id
               where
               t1.pay_status > 0 and
               t2.shop_id = {$shopid}
               order by t1.pay_title";
      return $CON->select($sql);
   }

   $sql = " select *
            from payments
            where
            pay_status > 0
            order by pay_title";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getPaymentsIn($CON)
{
   $sql = " select *
            from payments_in
            where
            pay_status > 0
            order by pay_title";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getGiros($CON)
{
   $sql = " select *
            from giros
            where
            giro_status > 0
            order by giro_name";
   return $CON->select($sql);
}

function getBancos($CON)
{

   $sql = " select *
            from parametros
            where
            tabla = 'BANCOS' 
            order by codigo";
   return $CON->select($sql);

}

function getTipoCuentas($CON)
{

   $sql = " select *
            from parametros
            where
            tabla = 'TIPOCUENTA' 
            order by codigo";
   return $CON->select($sql);

}


//----------------------------------------------------------------------------------
function getContacto($CON)
{
   $sql = " select * 
            from customer_contacts
            order by add_lastname" ;
            
   return $CON->select($sql);
}
//----------------------------------------------------------------------------------
function getSellers($CON)
{
   global $_sesmodulename;
   $sql = " select t1.*
            from user t1
            INNER JOIN user_group t2 ON t1.id = t2.user_id ";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $sql .= " INNER JOIN user_companies t3 ON t1.id = t3.user_id ";
   $sql .= " where
             t2.group_id    = 18 and
             t1.user_status = 1 ";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $sql .= " and t3.company_id  = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   $sql .= " order by t1.user_firstname, t1.user_lastname";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function getTransports($CON)
{
   $sql = " select *
            from transports
            where
            trans_status = 1
            order by trans_name asc";
   return $CON->select($sql);
}

//----------------------------------------------------------------------------------
function showOrderSellerNotice($CON, $sellerid, $custid)
{
   $sql = " select t1.cust_sellerid, t2.user_firstname, t2.user_lastname
            from customer t1
            INNER JOIN user t2 ON t1.cust_sellerid = t2.id
            where
            t1.id = {$custid}";
   $cust_sellerid = $CON->select($sql);
   $cust_sellerid = $cust_sellerid[0];

   if($sellerid > 0)
   {
      if($sellerid != (int)$cust_sellerid["cust_sellerid"])
      {  ?>
         <div style="margin:0px; padding-top:5px;color:red"><img
         border="0" src="./images/menu/icons/exclamation-button.png"
         style="vertical-align:bottom;padding-right:3px">REEMPLAZO POR <?=$cust_sellerid["user_firstname"]?> <?=$cust_sellerid["user_lastname"]?></div>
         <?php
      }
   }
}

//----------------------------------------------------------------------------------
function sellerHasReplacement($CON, $userid, $date)
{
   $sql = " select rep_userchange_id, rep_userchange_id2
            from replacement_seller
            where
            rep_user_id    = {$userid} and
            rep_startdate <= {$date} and
            rep_enddate   >= {$date} and
            rep_status     = 1";
   $repluser = $CON->select($sql);

   $_RET["SELLER"] = (int)$repluser[0]["rep_userchange_id"];
   $_RET["CASHER"] = (int)$repluser[0]["rep_userchange_id2"]; 
   return $_RET;
}

//----------------------------------------------------------------------------------
function printSortLink($_sesmodulename, $_sortlinks, $idx, $add = "")
{
   $prefix  = "";
   $suffix  = "";
   $keys    = array_keys($_sortlinks);
   $idxname = $keys[$idx];

   if(str_replace(" ", "", str_replace(" desc", "", str_replace(" asc", "", $_SESSION[$_sesmodulename]["orderBy"]))) == $_sortlinks[$idxname])
   {
      $prefix = "<b>";
      $suffix = "</b>";
   }
   ?>
   <a class="link" href="index.php?mid=<?=$_REQUEST["mid"]?>&orderBy=<?=$_sortlinks[$idxname]?><?=$add?>"><?=$prefix.$idxname.$suffix?></a>
   <?php
}

//----------------------------------------------------------------------------------
function printOverviewCompanySelect($companies, $_sesmodulename)
{
   global $_LANG;
   ?>
   <select class="text" name="sql_company" style="width:375px"
   onmousedown="markfield(this,0)" onblur="markfield(this,1)"
   onchange="setCompanyShop(this.value)">
      <?php
      foreach($companies AS $company)
      {  ?>
         <option value="<?=$company["id"]?>"
         <?php if($company["id"] == $_SESSION[$_sesmodulename]["sql_company"]) echo "selected"?>><?=$company["company_short"]?></option><?php
      }
      ?>
   </select>
   <?php
}

//----------------------------------------------------------------------------------
function printOverviewSupplierSelect($suppliers, $_sesmodulename)
{
   global $_LANG;
   global $CON;
   ?>
   <script language="JavaScript">
      function detectSuppEvent (event)
      {
         var xurl = './libs/modules/supplier_order/searchsupplier.fancy.php?destobj=sql_supplier'
         var keyCode = ('which' in event) ? event.which : event.keyCode;
         if(keyCode == 112)
            showFancybox(xurl, 'iframe', 1000, 450, 'auto');
      }
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td width="105">
         <input type="text" class="text" style="width:100px" onfocus="markfield(this,0)"
         name="supplier_search" id="supplier_search"
         onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/items/searchsupplier.php?destobj=sql_supplier&rowcount=0' +'&search=' +this.value;"
         onkeyup="detectSuppEvent(event)">
      </td>
      <td>
         <select class="text" name="sql_supplier" style="width:270px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <?php
            if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
            {
               $sql = " select *
                        from supplier
                        where
                        id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
               $supplier = $CON->select($sql);
               $supplier = $supplier[0];
               ?>
               <option value="<?=$_SESSION[$_sesmodulename]["sql_supplier"]?>"><?=$supplier["supp_company"]?></option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   </table>
   <?php
}

//----------------------------------------------------------------------------------
function printOverviewItemSelect($_sesmodulename)
{
   global $_LANG;
   global $CON;
   ?>
   <script language="JavaScript">
      function detectItemEvent (event, mode)
      {
         var xurl = './libs/modules/items/searchitem.fancy.php?destobj=xf_itemsearch&mode=' +mode
         var keyCode = ('which' in event) ? event.which : event.keyCode;
         if(keyCode == 112)
            showFancybox(xurl, 'iframe', 1000, 450, 'auto');
      }
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <colgroup>
      <col width="105">
      <col>
   </colgroup>
   <tr>
      <td>
         <input type="text" class="text" style="width:100px" onfocus="markfield(this,0)" name="xf_itemsearch" id="idx_xf_itemsearch"
         onblur="markfield(this,1);if(this.value != '') document.all.idxifrsrc.src='./libs/modules/stats/searchitem.php?search=' +this.value"
         onkeyup="detectItemEvent(event, 'stats')" value="<?=$_REQUEST["xf_itemsearch"]?>">
      </td>
      <td>
         <select class="text" style="width:270px" name="item_id"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <?php
            if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
            {
               if($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
               {
                  $sql = " select *
                           from item
                           where
                           id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
                  $item = $CON->select($sql);
                  $item = $item[0];
               }
               else
               {
                  $sql = " select *
                           from itemlist
                           where
                           id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
                  $item = $CON->select($sql);
                  $item = $item[0];
               }

               $desc       = trim(addslashes($item["item_title"]));
               $unitdesc   = getItemUnitDesc($CON, $_SESSION[$_sesmodulename]["sql_item_id"], $_SESSION[$_sesmodulename]["sql_item_type"]);
               ?>
               <option value="<?=$_SESSION[$_sesmodulename]["sql_item_id"]?>#<?=$_SESSION[$_sesmodulename]["sql_item_type"]?>">
                  <?=$item["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)
               </option>
               <?php
               if($_REQUEST["xf_itemsearch"] != "")
               {
                  $excludeitemid = (int)$item["id"];
                  $_REQUEST["xf_itemsearch"] = trim(addslashes($_REQUEST["xf_itemsearch"]));

                  $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'I'
                           from item t1
                           LEFT OUTER JOIN item_suppliers t2 ON t1.id = t2.item_id
                           LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
                           where
                           t1.item_status       = 1 and
                           t1.item_released     = 1 and
                           (
                              t1.item_title        like '%{$_REQUEST["xf_itemsearch"]}%' or
                              t1.item_number       like '%{$_REQUEST["xf_itemsearch"]}%' or
                              t1.item_number_prod  like '%{$_REQUEST["xf_itemsearch"]}%' or
                              t2.item_code         like '%{$_REQUEST["xf_itemsearch"]}%' or
                              tx.item_barcode      = '{$_REQUEST["xf_itemsearch"]}'
                           ) and
                           t1.id != {$excludeitemid}
                           order by 2
                           LIMIT 0, 200";
                  $items = $CON->select($sql);
                  for($x = 0; $x < count($items) && $items != false; $x++)
                  {
                     if($items[$x]["item_type"] == "item_typeI")
                        $items[$x]["item_type"] = "item";
                     else
                        $items[$x]["item_type"] = "itemlist";

                     $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
                     $desc          = trim(addslashes($items[$x]["item_title"]));
                     ?>
                     <option value="<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>">
                        <?=$items[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)
                     </option>
                     <?php
                  }
               }
            }
            ?>
         </select>
      </td>
   </tr>
   </table>
   <?php
}

//----------------------------------------------------------------------------------
function printOverviewCustomerSelect($_sesmodulename)
{
   global $_LANG;
   global $CON;
   ?>
   <script language="JavaScript">
      function detectCustEvent (event, mode)
      {
         var xurl = './libs/modules/orders/searchcust.fancy.php?destobj=sql_customer&inpobj=xf_custsearch&mode=' +mode
         var keyCode = ('which' in event) ? event.which : event.keyCode;
         if(keyCode == 112)
            showFancybox(xurl, 'iframe', 1000, 450, 'auto');
      }
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <colgroup>
      <col width="105">
      <col>
   </colgroup>
   <tr>
      <td>
         <input type="text" class="text" style="width:100px" onfocus="markfield(this,0)" name="xf_custsearch"
         onblur="markfield(this,1);if(this.value != '') document.all.idxifrsrc.src='./libs/modules/orders/searchcust.php?rowcount=0&destobj=sql_customer&search=' +this.value"
         onkeyup="detectCustEvent(event, 'orders')">
      </td>
      <td>
         <select class="text" style="width:270px" name="sql_customer"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <?php
            if((int)$_SESSION[$_sesmodulename]["sql_customer"])
            {
               $sql = " select cust_name
                        from customer
                        where
                        id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
               $selcustomer = $CON->select($sql);
               ?>
               <option value="<?=$_SESSION[$_sesmodulename]["sql_customer"]?>">
                  <?=$selcustomer[0]["cust_name"]?>
               </option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   </table>
   <?php
}

//----------------------------------------------------------------------------------
function printOverviewShopSelect($shops, $_sesmodulename)
{
   global $_LANG;
   if($_SESSION[$_sesmodulename]["sql_company"])
   {
      $selshops = Array();
      foreach($shops AS $shop)
         if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
            array_push($selshops, $shop);
   }
   ?>
   <select class="text" name="sql_shop" style="width:375px"
   onmousedown="markfield(this,0)" onblur="markfield(this,1)">
      <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
      <?php
      foreach($selshops AS $selshop)
      {  ?>
         <option value="<?=$selshop["id"]?>"
         <?php if($selshop["id"] == $_SESSION[$_sesmodulename]["sql_shop"]) echo "selected"?>><?=$selshop["shop_name"]?>
         </option><?php
      }
      ?>
   </select>
   <?php
}

//----------------------------------------------------------------------------------
function printOverviewPeriodSelect($_sesmodulename)
{  ?>
   <input type="text" style="width:70px" id="sql_datefrom" name="sql_datefrom"
   class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
   onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_SESSION[$_sesmodulename]["sql_datefrom"]?>">
   &nbsp;-&nbsp;
   <input type="text" style="width:70px" id="sql_dateto" name="sql_dateto"
   class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
   onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_SESSION[$_sesmodulename]["sql_dateto"]?>">
   <?php
}

//----------------------------------------------------------------------------------
function printOverviewResults($results)
{  ?>
   <table border="0" cellpadding="0" cellspacing="0">
   <colgroup>
      <col width="20">
      <col>
   </colgroup>
   <tr>
      <td class="content_row_clear"><img src="./images/menu/icons/information-frame.png"></td>
      <td class="content_row_clear" align="right"><nobr><b><?=(int)$results?> Resultados</b></nobr></td>
   </tr>
   </table>
   <?php
}

//----------------------------------------------------------------------------------
function getDateFromString($dstr, $dayinit = true)
{
   if($dayinit)
   {
      $hour = 0;
      $min  = 0;
      $sec  = 0;
   }
   else
   {
      $hour = 23;
      $min  = 59;
      $sec  = 59;
   }
   $dstr = explode(".", trim($dstr));
   $dstr = (int)mktime($hour, $min, $sec, $dstr[1], $dstr[0], $dstr[2]);
   return $dstr;
}

//----------------------------------------------------------------------------------
function printJSsetCompanyShop($shops)
{  ?>
   <script language="JavaScript">
      function setCompanyShop(companyidx)
      {
         var obj = document.all.sql_shop;
         obj.options.length = 1;
         <?php
         foreach($shops AS $shop)
         {  ?>
            if(companyidx == '<?=$shop["shop_company_id"]?>')
            {
               var newIndex   = obj.options.length;
               var newOpt     = new Option('<?=addslashes($shop["shop_name"])?>');
               newOpt.value   = '<?=$shop["id"]?>';
               obj.options[newIndex] = newOpt;
            }
            <?php
         }
         ?>
      }
   </script>
   <?php
}

//----------------------------------------------------------------------------------
function getCatidFromObj($CON, $objid, $objtype)
{
   $sql = " select cat_id
            from item_productcats{$objtype}
            where
            item_id = {$objid}";
   $catid = $CON->select($sql);

   return (int)$catid[0]["cat_id"];
}

//----------------------------------------------------------------------------------
function printButton($bname, $btype, $burl, $bclick, $bicon, $tablewidth = "", $id = "")
{
   if($tablewidth != "")
   {  ?><table border="0" width="<?=$tablewidth?>" cellpadding="0" cellspacing="0"><tr><td class="xcontent_row_clear" align="center"><?php
   }
   ?><ul ondragstart="return false" class="<?=$btype?>" <?php if($id != "") echo "id='{$id}'"?>><a href="<?=$burl?>" <?php if($bclick != "") echo "onclick=\"{$bclick}\""?>><?php
   if($bicon != "")
   {  ?><div style="z-index:0;font-family:Arial;font-size:12px;font-weight:bold;padding-left:3px;padding-right:3px;"><nobr><img height="14" border="0" src="/images/menu/icons/<?=$bicon?>.png" style="vertical-align:middle"> <?=$bname?></nobr></div><?php
   }
   else
      echo $bname;
   ?></a></ul><?php
   if($tablewidth != "")
   {  ?></td></tr></table><?php
   }
}


//----------------------------------------------------------------------------------
function generateCountryJS($countries, $regions, $comunas, $provincias)
{
   ?>
   function selectSelItem(idx, idxval)
   {
      var obj = document.getElementById(idx);

      for(var x = 0; x < obj.options.length; x++)
         if(obj.options[x].value == idxval)
            obj.selectedIndex = x;
   }
   function setRegions(cid)
   {
      var regobj = document.getElementById('regions');
      var comobj = document.getElementById('comunas');
      var provobj = document.getElementById('provincias');
      regobj.options.length = 1;
      comobj.options.length = 1;
      provobj.options.length = 1;
      <?php
      foreach($countries AS $country)
      {  ?>
         if(cid == '<?=$country["id"]?>')
         {  <?php
            foreach($regions AS $region)
            {
               if($region["id_pais"] == $country["id"])
               {  ?>
                  var newIndex = regobj.options.length;
                  var newOpt = new Option('<?=$region["name"]?>');
                  newOpt.value = '<?=$region["id"]?>';
                  regobj.options[newIndex] = newOpt;
                  <?php
               }
            }
            ?>
         }
         <?php
      }
      ?>
   }
   function setProvincias(rid)
   {
      var regobj = document.getElementById('regions');
      var comobj = document.getElementById('comunas');
      var provobj = document.getElementById('provincias');
      comobj.options.length = 1;
      provobj.options.length = 1;
      <?php
      foreach($regions AS $region)
      {  ?>
         if(rid == '<?=$region["id"]?>')
         {  <?php
            foreach($provincias AS $provincia)
            {
               if($provincia["region_id"] == $region["id"])
               {  ?>
                  var newIndex = provobj.options.length;
                  var newOpt = new Option('<?=$provincia["pro_name"]?>');
                  newOpt.value = '<?=$provincia["id"]?>';
                  provobj.options[newIndex] = newOpt;
                  <?php
               }
            }
            ?>
         }
         <?php
      }
      ?>
   }
   function setComunas(pid)
   {
      var regobj = document.getElementById('regions');
      var comobj = document.getElementById('comunas');
      var provobj = document.getElementById('provincias');
      comobj.options.length = 1;
      <?php
      foreach($comunas AS $comuna)
      {  ?>
         if(pid == '<?=$comuna["prov_id"]?>')
         {
            var newIndex = comobj.options.length;
            var newOpt = new Option('<?=$comuna["nombre"]?>');
            newOpt.value = '<?=$comuna["id"]?>';
            comobj.options[newIndex] = newOpt;
         }
         <?php
      }
      ?>
   }
   <?php
}

//----------------------------------------------------------------------------------
function cfAdd($fecha)
{
   $temp = explode(".",$fecha);
   if($fecha)
   {
     $fecha = $temp[2]."-".$temp[1]."-".$temp[0];
   }  
   return $fecha;
}

//----------------------------------------------------------------------------------
function cf($fecha)
{
   $temp = explode("-",$fecha);
   if($fecha)
   {
      $fecha = $temp[2].".".$temp[1].".".$temp[0];
   }  
   return $fecha;
}

//----------------------------------------------------------------------------------
function getSex($type)
{
   switch($type)
   {
      case "M": return "Masculino"; break;
      case "F": return "Femenino"; break;
      case "I": return "Infante"; break;
      default:  return "Desconocido";
   }
}

//----------------------------------------------------------------------------------
function Num2Text($value)
{
   switch($value)
   {
      case 0: $Num2Text = "CERO"; break;
      case 1: $Num2Text = "UN"; break;
      case 2: $Num2Text = "DOS"; break;
      case 3: $Num2Text = "TRES"; break;
      case 4: $Num2Text = "CUATRO"; break;
      case 5: $Num2Text = "CINCO"; break;
      case 6: $Num2Text = "SEIS"; break;
      case 7: $Num2Text = "SIETE"; break;
      case 8: $Num2Text = "OCHO"; break;
      case 9: $Num2Text = "NUEVE"; break;
      case 10: $Num2Text = "DIEZ"; break;
      case 11: $Num2Text = "ONCE"; break;
      case 12: $Num2Text = "DOCE"; break;
      case 13: $Num2Text = "TRECE"; break;
      case 14: $Num2Text = "CATORCE"; break;
      case 15: $Num2Text = "QUINCE"; break;
      case ($value < 20): $Num2Text = "DIECI" . Num2Text($value - 10); break;
      case 20: $Num2Text = "VEINTE"; break;
      case ($value < 30): $Num2Text = "VEINTI" . Num2Text($value - 20); break;
      case 30: $Num2Text = "TREINTA"; break;
      case 40: $Num2Text = "CUARENTA"; break;
      case 50: $Num2Text = "CINCUENTA"; break;
      case 60: $Num2Text = "SESENTA"; break;
      case 70: $Num2Text = "SETENTA"; break;
      case 80: $Num2Text = "OCHENTA"; break;
      case 90: $Num2Text = "NOVENTA"; break;
      case ($value < 100): $Num2Text = Num2Text((int)($value / 10) * 10) . " Y " . Num2Text($value % 10); break;
      case 100: $Num2Text = "CIEN"; break;
      case ($value < 200): $Num2Text = "CIENTO " . Num2Text($value - 100); break;
      case ($value == 200 || $value == 300 || $value == 400 || $value == 600 || $value == 800):
           $Num2Text = Num2Text((int)($value / 100)) . "CIENTOS"; break;
      case 500: $Num2Text = "QUINIENTOS"; break;
      case 700: $Num2Text = "SETECIENTOS"; break;
      case 900: $Num2Text = "NOVECIENTOS"; break;
      case ($value < 1000): $Num2Text = Num2Text((int)($value / 100) * 100) . " " . Num2Text($value % 100); break;
      case 1000: $Num2Text = "MIL"; break;
      case ($value < 2000): $Num2Text = "MIL " . Num2Text($value % 1000); break;
      case ($value < 1000000):
          $Num2Text = Num2Text((int)($value / 1000)) . " MIL";
          if($value % 1000)
             $Num2Text = $Num2Text . " " . Num2Text($value % 1000);
          break;
      case 1000000: $Num2Text = "UN MILLON"; break;
      case ($value < 2000000): $Num2Text = "UN MILLON " . Num2Text($value % 1000000); break;
      case ($value < 1000000000000):
          $Num2Text = Num2Text((int)($value / 1000000)) . " MILLONES ";
          if(($value - (int)($value / 1000000) * 1000000))
             $Num2Text = $Num2Text . " " . Num2Text($value - (int)($value / 1000000) * 1000000);
          break;
      case 1000000000000: $Num2Text = "UN BILLON"; break;
      case ($value < 2000000000000):
          $Num2Text = "UN BILLON " . Num2Text($value - (int)($value / 1000000000000) * 1000000000000);
          break;
      default:
          $Num2Text = Num2Text((int)($value / 1000000000000)) & " BILLONES";
          if(($value - (int)($value / 1000000000000) * 1000000000000))
             $Num2Text = $Num2Text . " " . Num2Text($value - (int)($value / 1000000000000) * 1000000000000);
          break;
   }
   return $Num2Text;

}


//----------------------------------------------------------------------------------
function printProdSubcats($CON, $cat_parentid, $level, $search = "")
{
   global $_LANG;
   
   $level++;
   
   $sql = " select   t1.*,
                     t2.user_lastname 'crtusr_name',
                     t3.user_lastname 'updusr_name'
            from productcats{$_REQUEST["tbl_suffix"]} t1
            LEFT OUTER JOIN user t2 ON t1.cat_crtusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.cat_updusr = t3.id
            where
            cat_parentid   = {$cat_parentid} and
            cat_status     = 1
            order by t1.id asc";
   $data = $CON->select($sql);

   for($x = 0; $x < count($data) && $data != false; $x++)
   {
      if((int)$data[$x]["cat_released"] == 0)
         $img_status = "status_red.gif";
      else
         $img_status = "status_green.gif";

      $colorbase  = -1;
      $colorthis  = $colorbase + (0.03 * ($level -1));
      $colorhex   = colourBrightness("#FFFFFF", $colorthis);

      if($level == 1)
         $padding = "5px"; 
      else
         $padding = (($level * 11) -10)."px";
      
      if($level > 1)
         $addimg = "paging_next.gif";
      else
         $addimg = "";
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=sprintf("%03s", $data[$x]["id"])?></td>
         <td class="content_row" style="padding-left:<?=$padding?>">
            <?php
            if($addimg != "")
            {  ?>
               <img src="./images/content/<?=$addimg?>">&nbsp;
               <?php
            }
            ?>
            <?=$data[$x]["cat_title"]?>&nbsp;
         </td>
         <td class="content_row"><?=displayDate($data[$x]["cat_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&subexec=update&id={$data[$x]["id"]}&tbl_suffix={$_REQUEST["tbl_suffix"]}", "", "pencil");
            ?>
         </td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=delete&id={$data[$x]["id"]}&tbl_suffix={$_REQUEST["tbl_suffix"]}')", "cross-circle-frame");
            ?>
         </td>
      </tr>
      <?php
      printProdSubcats($CON, $data[$x]["id"], $level);
   }
}

//----------------------------------------------------------------------------------
function formatFullProductCats($data, $retarr = Array())
{
   for($x = 0; $x < count($data) && $data != false; $x++)
   {
      array_push($retarr, $data[$x]);
      if((int)$data[$x]["subcatcount"])
      {
         $temparr = formatFullProductCats($data[$x]["subitems"]);
         for($y = 0; $y < count($temparr) && $temparr != false; $y++)
         {
            $temparr[$y]["cat_title"] = $data[$x]["cat_title"]." > ".$temparr[$y]["cat_title"];
            array_push($retarr, $temparr[$y]);
         }
      }
   }
   return $retarr;
}

//----------------------------------------------------------------------------------
function getFullProductCats($CON, $cat_parentid)
{
   $sql = " select   t1.id, t1.cat_title, t1.cat_parentid
            from productcats{$_REQUEST["tbl_suffix"]} t1
            where
            t1.cat_status     = 1
            order by t1.cat_title asc ";
   $data = $CON->select($sql);

   for($x = 0; $x < count($data) && $data != false; $x++)
   {
      if((int)$data[$x]["subcatcount"])
         $data[$x]["subitems"] = getFullProductCats($CON, $data[$x]["id"]);
   }

   return $data;
}

//----------------------------------------------------------------------------------
function printProdSubcatsSelect($CON, $cat_parentid, $level, $catids)
{
   global $_LANG;
   
   $level++;
   
   $sql = " select   t1.*,
                     t2.user_lastname 'crtusr_name',
                     t3.user_lastname 'updusr_name',
                     count(t4.id) 'subcatcount'
            from productcats t1
            LEFT OUTER JOIN user t2 ON t1.cat_crtusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.cat_updusr = t3.id
            LEFT OUTER JOIN productcats t4 ON (t1.id = t4.cat_parentid and t4.cat_status = 1)
            where
            t1.cat_parentid   = {$cat_parentid} and
            t1.cat_status     = 1
            group by t1.id
            order by t1.id asc";
   $data = $CON->select($sql);

   for($x = 0; $x < count($data) && $data != false; $x++)
   {
      $colorbase  = -1;
      $colorthis  = $colorbase + (0.03 * ($level -1));
      $colorhex   = colourBrightness("#FFFFFF", $colorthis);

      if($level == 1)
         $padding = "5px"; 
      else
         $padding = (($level * 11) -10)."px";
      
      if($level > 1)
         $addimg = "paging_next.gif";
      else
         $addimg = "";
      ?>
      <tr bgcolor="<?=$colorhex?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center" height="26">
               <input type="radio" name="catids[]" id="chkbox_<?=$data[$x]["id"]?>" value="<?=$data[$x]["id"]?>"
               <?php if(array_search($data[$x]["id"], $catids) !== false) echo "checked"?>>
         </td>
         <td class="content_row"><?=sprintf("%03s", $data[$x]["id"])?></td>
         <td class="content_row" style="padding-left:<?=$padding?>">
            <?php
            if($addimg != "")
            {  ?>
               <img src="../../../images/content/<?=$addimg?>">&nbsp;
               <?php
            }
            ?>
            <?=$data[$x]["cat_title"]?>&nbsp;
         </td>
         
         <td class="content_row"><?=displayDate($data[$x]["cat_crtdat"])?></td>
         <td class="content_row">
            <ul class="postnav">
               <a href="javascript: document.all.chkbox_<?=$data[$x]["id"]?>.checked = true; setProductcats('<?=$_REQUEST["tbl_suffix"]?>')"><?=$_LANG["FORM"]["BUTTON"][8]?></a>
            </ul>
         </td>
      </tr>
      <?php
      printProdSubcatsSelect($CON, $data[$x]["id"], $level, $catids);
   }
}

//----------------------------------------------------------------------------------
function printProdSubcatsList($CON, $catid)
{
   $sql = " select t1.cat_title 'catdesc', t1.cat_parentid
            from productcats t1
            where
            t1.id          = {$catid} and
            t1.cat_status  = 1";
   $catdata = $CON->select($sql);

   $catstr = "{$catdata[0]["catdesc"]} &gt; " .$catstr;
   if((int)$catdata[0]["cat_parentid"])
      $catstr = printProdSubcatsList($CON, $catdata[0]["cat_parentid"]) . $catstr;
   return $catstr;
}


//----------------------------------------------------------------------------------
function getCustomerTaxes($CON, $custid)
{
   if((int)$custid == 0)
      return true;
      
   $sql = " select cust_countryid
            from customer
            where
            id = {$custid}";
   $custdata = $CON->select($sql);
   $custdata = $custdata[0];
   
   $sql = " select taxes_active
            from country
            where
            id = {$custdata["cust_countryid"]}";
   $country_taxes = $CON->select($sql);
   $country_taxes = (int)$country_taxes[0]["taxes_active"];

   if($country_taxes)
      return true;
   return false;
}

//----------------------------------------------------------------------------------
function csv_explode($delim=',', $str, $enclose='"', $preserve=false)
{
  $resArr = array();
  $n = 0;
  $expEncArr = explode($enclose, $str);
  foreach($expEncArr as $EncItem)
  {
    if($n++%2)
    {
      array_push($resArr, array_pop($resArr) . ($preserve?$enclose:'') . $EncItem.($preserve?$enclose:''));
    }
    else
    {
      $expDelArr = explode($delim, $EncItem);
      array_push($resArr, array_pop($resArr) . array_shift($expDelArr));
      $resArr = array_merge($resArr, $expDelArr);
    }
  }
  return $resArr;
}

//----------------------------------------------------------------------------------
function Nifty_printJS($type = "")
{
   return "unhideWindow();hideLoading();";
   return $retstr;
}

//----------------------------------------------------------------------------------
function Nifty_printH($elem, $width, $id)
{
   if($elem == "boxopt_b")
      $_SESSION["_SUBMITBTN"] = 1;

   if($elem == "box2" || $elem == "box3")
      $elem = "box1";
   
   return "<table border='0' cellpadding='0' cellspacing='0' width='{$width}'><tr><td valign='top' style='padding-right:3px'><div class='{$elem}' id='{$id}'>";
}

//----------------------------------------------------------------------------------
function Nifty_printF($bpadding = true)
{
   if(!$bpadding && $_SESSION["_SUBMITBTN"] == 1)
   {
      $retstr .= "<input type='submit' value='' style='position:absolute;top:0px;left:0px;width:1px;height:1px;border:none;background-color:#FFFFFF;border-color:#FFFFFF'>";
      $_SESSION["_SUBMITBTN"] = 0;
   }
   $retstr .= "</div></td></tr></table>";

   return $retstr;
}

//----------------------------------------------------------------------------------
function addPublicAppointment($CON, $startdate, $cal_header, $cal_body, $cust_id = 0, $supp_id = 0)
{
   $currtme = time();
   
   $day_begin  = $startdate + (7 * 3600);
   $day_end    = $startdate + (86400 -1800);

   $sql = " select cal_startdate
            from calendar_appointments
            where
            cal_startdate between {$day_begin} and {$day_end} ";
   $occupiedhours = $CON->select($sql);

   for($x = 0; $x < count($occupiedhours) && $occupiedhours != false; $x++)
   {
      $hour = (int)date('H', $occupiedhours[$x]["cal_startdate"]);
      $occh[$hour] = 1;
   }

   $cal_starthour = 0;
   
   for($x = 7; $x <= 23 && $cal_starthour == 0; $x++)
      if(!(int)$occh[$x])
         $cal_starthour = $x;

   if($cal_starthour == 0)
      $cal_starthour = rand(7,23);
      
   $cal_startdate = $startdate + ($cal_starthour * 3600);
   $cal_enddate   = $cal_startdate + 1800;

   $sql = " insert into calendar_appointments
           (cal_startdate, cal_enddate, cal_crtusr,
            cal_crtdat, cal_header, cal_body,
            cal_startday, cal_startmonth, cal_startyear,
            cal_endday, cal_endmonth, cal_endyear,
            cal_docid, cal_type, cal_custid, cal_suppid, cal_public)
           VALUES
           ({$cal_startdate}, {$cal_enddate}, {$_SESSION["user_id"]},
            {$currtme}, '{$cal_header}', '{$cal_body}',
            ".date('d', $cal_startdate).", ".date('m', $cal_startdate).", ".date('Y', $cal_startdate).",
            ".date('d', $cal_startdate).", ".date('m', $cal_startdate).", ".date('Y', $cal_startdate).",
            0, 1, {$cust_id}, {$supp_id}, 1)";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function deleteFilesInDir($path)
{
   if ($handle = opendir($path))
   {
      while (false !== ($file = readdir($handle)))
         if($file != "." && $file != "..")
            unlink("{$path}{$file}");
      closedir($handle);
   }
}

//----------------------------------------------------------------------------------
function countFilesInDir($path)
{
   $filecounter = 0;
   if ($handle = opendir($path))
   {
      while (false !== ($file = readdir($handle)))
         if($file != "." && $file != "..")
            $filecounter++;
      closedir($handle);
   }
   return $filecounter;
}

//----------------------------------------------------------------------------------
function setFilePrivileges($path, $mode)
{
   $mode = "0{$mode}";
   $mode = octdec($mode);
   
   if ($handle = opendir($path))
   {
      while (false !== ($file = readdir($handle)))
         if($file != "." && $file != "..")
            chmod("{$path}{$file}", $mode);
      closedir($handle);
   }
}

//----------------------------------------------------------------------------------
function imagecolorize ($imorig, $imnew, $pct, $pfad, $r=0, $g=0, $b=0)
{
   $im   = imagecreatefromjpeg($pfad.$imorig);
   $im_w = imagesx ($im);
   $im_h = imagesy ($im);

   $layover = imagecreate ($im_w ,$im_h ); 
   $color   = imagecolorallocate ($layover ,$r ,$g ,$b);   
   $fill    = imagefill ($layover ,0,0,$color );
   $merge   = imagecopymerge ($im ,$layover ,0,0,0,0,$im_w ,$im_h ,$pct ); 

   imagedestroy($layover );
   imagejpeg($im,$pfad.$imnew,100);
}

//----------------------------------------------------------------------------------
function xImageFlip($imgsrc, $mode)
{
    $width                        =    imagesx ( $imgsrc );
    $height                       =    imagesy ( $imgsrc );
    $src_x                        =    0;
    $src_y                        =    0;
    $src_width                    =    $width;
    $src_height                   =    $height;
    
    switch ( $mode )
    {
        case '1': //vertical
            $src_y                =    $height -1;
            $src_height           =    -$height;
        break;
        case '2': //horizontal
            $src_x                =    $width -1;
            $src_width            =    -$width;
        break;
        case '3': //both
            $src_x                =    $width -1;
            $src_y                =    $height -1;
            $src_width            =    -$width;
            $src_height           =    -$height;
        break;
        default:
            return $imgsrc;

    }

    $imgdest = imagecreatetruecolor ( $width, $height );
    if ( imagecopyresampled ( $imgdest, $imgsrc, 0, 0, $src_x, $src_y , $width, $height, $src_width, $src_height ) )
        return $imgdest;
    return $imgsrc;
}

//----------------------------------------------------------------------------------
function createButton($path, $name, $width, $height)
{
   $doc_hash = md5(microtime());
   $doc_name = "design_button_{$doc_hash}.jpg";
   
   $im   = imagecreatefromjpeg($path.$name);
   $im_w = imagesx ($im);
   $im_h = imagesy ($im);

   $canvas = imagecreatetruecolor($width, $height);
   imagecopy($canvas, $im, 0, 0, 0, 0, 10, $im_h);
   imagecopy($canvas, $im, ($width - 10), 0, ($im_w - 10), 0, 10, $im_h);

   for($x = 10; $x < ($width - 10); $x++)
      imagecopy($canvas, $im, $x, 0, 11, 0, 1, $im_h);

   imagejpeg($canvas, $path.$doc_name, 100);

   return $doc_name;
}

//----------------------------------------------------------------------------------
function formatFilename($filename)
{
   $filename = str_replace("�", "Ae", $filename);
   $filename = str_replace("�", "Ue", $filename);
   $filename = str_replace("�", "Oe", $filename);
   $filename = str_replace("�", "ae", $filename);
   $filename = str_replace("�", "ue", $filename);
   $filename = str_replace("�", "oe", $filename);
   $filename = str_replace("�", "ss", $filename);
   $filename = str_replace("�", "n", $filename);
   $filename = str_replace("�", "N", $filename);
   $filename = str_replace("�", "A", $filename);
   $filename = str_replace("�", "a", $filename);
   $filename = str_replace("�", "c", $filename);
   $filename = str_replace("�", "E", $filename);
   $filename = str_replace("�", "e", $filename);
   $filename = str_replace("�", "I", $filename);
   $filename = str_replace("�", "i", $filename);
   $filename = str_replace("�", "O", $filename);
   $filename = str_replace("�", "o", $filename);
   $filename = str_replace("�", "U", $filename);
   $filename = str_replace("�", "u", $filename);
   
   $filename = preg_replace('/[ ]{2,}/sm', ' ', $filename);
   
   $filename = preg_replace("/[^a-zA-Z0-9 -]/", "", $filename);
   $filename = strtolower(trim($filename));
   $filename = str_replace(" ", "-", $filename);
   $filename = substr($filename, 0, 120);
   
   return $filename;
}

//----------------------------------------------------------------------------------
function resizeImage($filename,$max_width,$max_height='',$newfilename="",$withSampling = true,$bigger = false)
{
   if($newfilename=="")
       $newfilename=$filename;
       
   list($width, $height) = getimagesize($filename);
   
   if(!$bigger)
   {
      if($width<=$max_width)
         $max_width=$width;
   }
   
   $percent = $max_width/$width;
   
   $newwidth = $width * $percent;
   if($max_height=='')
       $newheight = $height * $percent;
   else
       $newheight = $max_height;
   
   $thumb = imagecreatetruecolor($newwidth, $newheight);
   $white = imagecolorallocate($thumb, 255, 255, 255);
   imagefilledrectangle($thumb, 0, 0, $newwidth, $newheight, $white);
   
   $ext = strtolower(substr($filename, strrpos($filename, ".") +1));
   
   if($ext=='jpg' || $ext=='jpeg')
       $source = imagecreatefromjpeg($filename);
   if($ext=='gif')
       $source = imagecreatefromgif($filename);
   if($ext=='png')
       $source = imagecreatefrompng($filename);
   
   if($withSampling)
       imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
   else
       imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
   
   if($ext=='jpg' || $ext=='jpeg')
       return imagejpeg($thumb,$newfilename,96);
   if($ext=='gif')
       return imagegif($thumb,$newfilename);
   if($ext=='png')
    return imagepng($thumb,$newfilename,1);
}

//----------------------------------------------------------------------------------
function createSiiNumber($CON, $companyid, $shopid, $transtype, $simulate = false)
{
   $sql = " select company_numcounter_{$transtype}, company_numcounter_{$transtype}_rinit, company_numcounter_{$transtype}_rend
            from company_data
            where
            id = {$companyid}";
   $ordernumber = $CON->select($sql);

   $nextfolio = $ordernumber[0]["company_numcounter_{$transtype}"] +1;
   $rangeinit = $ordernumber[0]["company_numcounter_{$transtype}_rinit"];
   $rangeend  = $ordernumber[0]["company_numcounter_{$transtype}_rend"];
   
   if($nextfolio < $rangeinit || $nextfolio > $rangeend)
      return false;

   //----------------------------------------------------------------------------------
   if(!$simulate)
   {
      $sql = " update company_data
               set
               company_numcounter_{$transtype} = company_numcounter_{$transtype} +1
               where
               id = {$companyid}";
      $res = $CON->no_result($sql);
   }
   else
      $res = true;

   //----------------------------------------------------------------------------------
   if($res)
   {
      return (int)$ordernumber[0]["company_numcounter_{$transtype}"] +1;
   }
   return false;
}


//----------------------------------------------------------------------------------
function createTransactionNumber($CON, $companyid, $transtype)
{
   $sql = " BEGIN";
   $CON->no_result($sql);

   $sql = " select company_numformat_{$transtype}, company_numcounter_{$transtype}
            from company_data
            where
            id = {$companyid}";
   $ordernumber = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " BEGIN WORK";
   $CON->no_result($sql);


   $sql = " update company_data
            set
            company_numcounter_{$transtype} = company_numcounter_{$transtype} +1
            where
            id = {$companyid}";
   $res = $CON->no_result($sql);
   
   $sql = "COMMIT";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   if($res)
   {
      $ordernumber = (int)$ordernumber[0]["company_numcounter_{$transtype}"] +1;
      $ordernumber = sprintf("%07s", $ordernumber);

      $sql = " select company_numformat_{$transtype}
               from company_data
               where
               id = {$companyid}";
      $order_number_format = $CON->select($sql);
      $order_number_format = $order_number_format[0]["company_numformat_{$transtype}"];
      $order_number_format = $order_number_format.$ordernumber;
      
      return $order_number_format;
   }
   return false;
}

function createNumberSystem($CON, $type)
{
   switch($type)
   {
      case "ITEMNUMBER":
         $field1 = "item_number_counter";
         $field2 = "item_number_format";
         break;
      case "ITEMLISTNUMBER":
         $field1 = "itemlist_number_counter";
         $field2 = "itemlist_number_format";
         break;
      case "VENTAINTERNA":
         $field1 = "vint_number_counter";
         $field2 = "vint_number_format";
         break;
      case "PRODOT":
         $field1 = "prod_number_counter";
         $field2 = "prod_number_format";
         break;
      case "USEDITEM":
         $field1 = "useditem_number_counter";
         $field2 = "useditem_number_format";
         break;
   }

   $sql = " BEGIN";
   $CON->no_result($sql);

   $sql = " select {$field1}
            from number_system";
   $ordernumber = $CON->select($sql);

   $sql = " BEGIN WORK";
   $CON->no_result($sql);


   $sql = " update number_system
            set {$field1} = {$field1} +1";
   $res = $CON->no_result($sql);

   $sql = "COMMIT";
   $CON->no_result($sql);

   if($res)
   {
      $ordernumber = (int)$ordernumber[0][$field1] +1;

      $sql = " select {$field2}
               from number_system";
      $order_number_format = $CON->select($sql);
      $order_number_format = $order_number_format[0][$field2];

      $numlen = strlen($ordernumber) *-1;
      $order_number_format = substr($order_number_format, 0, $numlen);
      $order_number_format = $order_number_format.$ordernumber;
      $order_number_format = str_replace("X","0", $order_number_format);

      return $order_number_format;
   }
   return false;
}

//----------------------------------------------------------------------------------
function updateUserStats($CON)
{
   $sql = " select counter
            from user_stats
            where
            user_id = {$_SESSION["user_id"]} and
            menu_id = {$_REQUEST["mid"]}";
   $counter = $CON->select($sql);

   if((int)$counter[0]["counter"])
      $sql = " update user_stats
               set
               counter = counter +1
               where
               user_id = {$_SESSION["user_id"]} and
               menu_id = {$_REQUEST["mid"]}";
   else
      $sql = " insert into user_stats
               (user_id, menu_id, counter)
               VALUES
               ({$_SESSION["user_id"]}, {$_REQUEST["mid"]}, 1)";

   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function printPageCounts($rows_per_page, $count, $current_page, $extras="" )
{
   $pagecount = (int)($count / $rows_per_page);
   if(($pagecount * $rows_per_page) < $count)
      $pagecount++;

   if($pagecount > 1)
   {
      $limite_per_row = 18 ;
      $inicio = ($current_page - 9);
      
      $fin = ($current_page + 9);
      if($fin < $limite_per_row)
         $fin = $limite_per_row;

      if($fin > $pagecount)
      {
         $inicio = $pagecount - $limite_per_row;
         $fin = $pagecount;
      }

      if($inicio < 0)
         $inicio = 0;
         
      $anterior = $current_page - 1;
      
      if($anterior < 0)
         $anterior = 0;

      ?>
      <table border="0" cellpadding="2" cellspacing="0" style="margin-top:1px;margin-bottom:1px">
      <tr>
      <td >
         <input type="button" class="button" style="width:30px;" value="<<" onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&page=0<?=$extras?>'"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
      </td>
      <td style='width:35px'>
         <input type="button" class="button" style="width:30px" value="<" onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&page=<?=$anterior?><?=$extras?>'"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
      </td>
      <?php
      for($x = $inicio; $x < $fin && $fin > 1; $x++)
      {
         if($x == $current_page)
            $bclass = "buttonactive";
         else
            $bclass = "button";

         if($x == $fin - 1)
          $ancho = "style='width:35px'";
         ?>

         <td <?=$ancho?>>
            <input type="button" class="<?=$bclass?>" style="width:30px" value="<?=$x +1?>"
            <?php
            if($bclass == "button")
            {  ?>
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
               <?php
            }
            ?>
            onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&page=<?=$x?><?=$extras?>'">
         </td>
         <?php
      }

      if(($current_page + 1) >= $pagecount)
         $siguiente = $pagecount - 1;
      else
         $siguiente = $current_page + 1;
      ?>
      <td>
         <input type="button" class="button" style="width:30px" value=">"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&page=<?=$siguiente?><?=$extras?>'" onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
      </td>
      <td>
         <input type="button" class="button" style="width:30px" value=">>"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&page=<?=$pagecount - 1?><?=$extras?>'" onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
      </td>
      <td class='content_row_clear'>( <?=$current_page+1?> / <?=$pagecount?> )</td>
      </tr>
      </table>
      <br>
      <?php
   }
}

/*
function printPageCounts($rows_per_page, $count, $current_page, $add)
{
   $pagecount = (int)($count / $rows_per_page);
   if(($pagecount * $rows_per_page) < $count)
      $pagecount++;
      
   for($x = 0; $x < $pagecount && $pagecount > 1; $x++)
   {  
      if(!$x)
      {  ?>
         <table border="0" cellpadding="2" cellspacing="0">
         <tr>
         <?php
      }

      if($x == $current_page)
         $bclass = "buttonactive";
      else
         $bclass = "button";
      ?>
      <td>
         <input type="button" class="<?=$bclass?>" style="width:27px" value="<?=$x +1?>"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?><?=$add?>&page=<?=$x?>'">
      </td><?php
      if($x > 0 && ($x +1) % 31 == 0)
      {  ?>
         </tr>
         <tr>
         <?php
      }
      if($x == $pagecount -1)
      {  ?>
         </tr>
         </table><?php
      }
   }
}
*/
//----------------------------------------------------------------------------------
function getAppointmentText($caldata, $groupstr = "", $userstr = "", $anonym)
{
   global $_LANG;

   //----------------------------------------------------------------------------------
   $weekdays[1]   = $_LANG["MODULE"]["CAL"][12];
   $weekdays[2]   = $_LANG["MODULE"]["CAL"][13];
   $weekdays[3]   = $_LANG["MODULE"]["CAL"][14];
   $weekdays[4]   = $_LANG["MODULE"]["CAL"][15];
   $weekdays[5]   = $_LANG["MODULE"]["CAL"][16];
   $weekdays[6]   = $_LANG["MODULE"]["CAL"][17];
   $weekdays[7]   = $_LANG["MODULE"]["CAL"][18];


   switch((int)$caldata["cal_type"])
   {
      case 1: $typestr = $_LANG["MODULE"]["CAL"][66]; break;
      case 2: $typestr = $_LANG["MODULE"]["CAL"][67]; break;
      case 3: $typestr = $_LANG["MODULE"]["CAL"][68]; break;
   }
   
   $dayofweek  = date('w', $caldata["cal_startdate"]);
   if($dayofweek == 0)
      $dayofweek = 7;
      
   $typestr    .= " / {$weekdays[$dayofweek]}";
   
   $retstr = '
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="850">
   <colgroup>
      <col width="170">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">'.$_LANG["MODULE"]["CAL"][48].'</td>
   </tr>
   <tr>
      <td class="content_row"><nobr>'.$_LANG["MODULE"]["CAL"][49].'</nobr></td>
      <td class="content_row">'.date('d.m.Y H:i', $caldata["cal_startdate"]).'</td>
   </tr>
   <tr>
      <td class="content_row"><nobr>'.$_LANG["MODULE"]["CAL"][50].'</nobr></td>
      <td class="content_row">'.date('d.m.Y H:i', $caldata["cal_enddate"]).'</td>
   </tr>
   <tr>
      <td class="content_row"><nobr>'.$_LANG["MODULE"]["CAL"][65].'</nobr></td>
      <td class="content_row">'.$typestr.'</td>
   </tr>
   <tr>
      <td class="content_row"><nobr>'.$_LANG["MODULE"]["CAL"][51].'</nobr></td>
      <td class="content_row">'.$caldata["user_firstname"].' '.$caldata["user_lastname"].'</td>
   </tr>
   </table>
   <br>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="850">
   <colgroup>
      <col width="170">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">'.$_LANG["MODULE"]["CAL"][52].'</td>
   </tr>';

   if($userstr != "" && !$anonym)
      $retstr .= '
      <tr>
         <td class="content_row"><nobr>'.$_LANG["MODULE"]["CAL"][53].'</nobr></td>
         <td class="content_row">'.$userstr.'</td>
      </tr>';
   
   if($groupstr != "" && !$anonym)
      $retstr .= '
      <tr>
         <td class="content_row"><nobr>'.$_LANG["MODULE"]["CAL"][54].'</nobr></td>
         <td class="content_row">'.$groupstr.'</td>
      </tr>';
      
   if(trim($caldata["cal_body"]) != "")
      $retstr .= '
      <tr>
         <td class="content_row" valign="top"><nobr>'.$_LANG["MODULE"]["CAL"][56].'</nobr></td>
         <td class="content_row">'.nl2br(stripslashes($caldata["cal_body"])).'</td>
      </tr>';

   return $retstr;
}

//----------------------------------------------------------------------------------
function sendUserPasswordMail($CON)
{
   global $_LANG;
   
   $sql = " select *
            from company_data";
   $company_data = $CON->select($sql);
   $company_data = $company_data[0];
   
   $title = $_LANG["MODULE"]["USER"][52];
   
   $body  = "{$_LANG["MODULE"]["USER"][53]} {$_REQUEST["user_firstname"]} {$_REQUEST["user_lastname"]},
             <br><br>
             {$_LANG["MODULE"]["USER"][54]} {$_SESSION["_CONF"]["conf_title"]}.
             <br><br>
             <b>{$_LANG["MODULE"]["USER"][55]}</b> {$_REQUEST["user_login"]}
             <br>
             <b>{$_LANG["MODULE"]["USER"][56]}</b> {$_REQUEST["user_pass1"]}
             <br><br>
             {$_LANG["MODULE"]["USER"][57]}
             <br>
             <a href='{$_SESSION["_CONF"]["conf_shopadmin_url"]}'>{$_SESSION["_CONF"]["conf_shopadmin_url"]}</a>
             <br><br>";

   sendExternalMail( $title, $body,
                     $_REQUEST["user_mail"], $_REQUEST["user_mail"],
                     $company_data["company_email"], $company_data["company_short"]);
}

//----------------------------------------------------------------------------------
function sendExternalMail($title, $body, $rcpt_addr, $rcpt_name, $from_addr = "", $from_name = "", $attachments = NULL)
{
   require_once('./libs/thirdparty/PHPMailer/src/Exception.php');
   require_once('./libs/thirdparty/PHPMailer/src/PHPMailer.php');
   require_once('./libs/thirdparty/PHPMailer/src/SMTP.php');
   
   // $rcpt_addr = "1bit.appelt@gmail.com";

   global $_CCOADDR;
   global $_OVERWRITE_MAILS;
   global $_OVERWRITENAME;
   global $CON;

   $sql = " select *
            from config_system";
   $conf = $CON->select($sql);
   $conf = $conf[0];

   if($conf["conf_mail_image"] != "")
      $body .= "<br><img src='".$conf["conf_shopadmin_url"]."images/companies/".$conf["conf_mail_image"]."'>";

   $from_addr = $_SESSION["_CONF"]["conf_mail_accountname"];
   $from_name = "{$_SESSION["user_firstname"]} {$_SESSION["user_lastname"]}";
   if($_OVERWRITENAME != "")
      $from_name = $_OVERWRITENAME;

   $mail = new PHPMailer\PHPMailer\PHPMailer();

   //    echo memory_get_usage()."<br>";
   $mail->SMTPDebug  = 0;
   $mail->isSMTP();
   $mail->Host       = $_SESSION["_CONF"]["conf_mailserver"];
   $mail->SMTPAuth   = true;
   $mail->Username   = $_SESSION["_CONF"]["conf_mail_accountname"];
   $mail->Password   = $_SESSION["_CONF"]["conf_mail_password"];
   $mail->SMTPSecure = false;
   $mail->SMTPAutoTLS = false;
   $mail->Port       = 25;
   $mail->setFrom($from_addr, $from_name);
   $mail->isHTML(true);
   $mail->Subject = $title;
   $mail->msgHTML($body);
   if($_SESSION["user_mail"] != "")
         $mail->addReplyTo($_SESSION["user_mail"], $from_name);

   if(count($_OVERWRITE_MAILS))
   {
      foreach($_OVERWRITE_MAILS AS $_OVERWRITE_MAIL)
         $mail->addAddress($_OVERWRITE_MAIL["ADDR"], $_OVERWRITE_MAIL["NAME"]);
   }
   else
      $mail->addAddress($rcpt_addr, $rcpt_name);

   foreach($_CCOADDR AS $_CCOADDROW)
   {
      $mail->addBCC($_CCOADDROW["ADDR"], $_CCOADDROW["NAME"]);
   }

   if(count($attachments) > 0)
   {
      foreach($attachments AS $attachment)
      {
         $xres = $mail->addAttachment($attachment["FILE"], $attachment["NAME"]);
      }
   }

   if(!$mail->send()) 
   {
      return false;
   }
   return true;
}

//----------------------------------------------------------------------------------
function getAppointmentHours($startime, $endtime)
{
   $diff = $endtime - $startime;
   $diff = $diff / 60 / 60;

   return (int)$diff +1;
}

//----------------------------------------------------------------------------------
function getVacationsOfYear($CON, $year, $userid)
{
   $sql = " select SUM(vac_days) 'vac_days'
            from vacation
            where
            vac_startyear  = {$year} and
            vac_crtusr     = {$userid} and
            vac_status     = 2";
   $vacas = $CON->select($sql);

   return (int)$vacas[0]["vac_days"];
}

//----------------------------------------------------------------------------------
function getVacations($CON, $month, $year, $userid)
{
   $sql = " select t1.id, t1.vac_startdate, t1.vac_enddate, t1.vac_status,
                   t2.reason_desc, t2.reason_short, t1.vac_crtdat, t1.vac_days, 
                   t1.vac_upddat, t3.user_firstname, t3.user_lastname
            from vacation t1
            INNER JOIN vacation_reasons t2 ON t1.vac_reasonid = t2.id
            LEFT OUTER JOIN user t3 ON t1.vac_updusr = t3.id
            where
            (
               (
                  t1.vac_startmonth = {$month} and
                  t1.vac_startyear  = {$year}
               ) or
               (
                  t1.vac_endmonth   = {$month} and
                  t1.vac_endyear    = {$year}
               ) or
               (
                  t1.vac_endmonth   > {$month} and
                  t1.vac_endyear    = {$year}
               ) or
               (
                  t1.vac_endyear    > {$year}
               )
            ) and
            t1.vac_crtusr = {$userid} ";
   $vacas = $CON->select($sql);

   for($x = 0; $x < count($vacas) && $vacas != false; $x++)
   {
      $startdate  = $vacas[$x]["vac_startdate"];
      $enddate    = $vacas[$x]["vac_enddate"];

      while($startdate <= $enddate)
      {
         $idx = date('d.m.Y', $startdate);
         
         $ret[$idx]["ID"]        = $vacas[$x]["id"];
         $ret[$idx]["DESC"]      = $vacas[$x]["reason_desc"];
         $ret[$idx]["SHRT"]      = $vacas[$x]["reason_short"];
         $ret[$idx]["STAT"]      = $vacas[$x]["vac_status"];
         $ret[$idx]["STARTDATE"] = $vacas[$x]["vac_startdate"];
         $ret[$idx]["ENDDATE"]   = $vacas[$x]["vac_enddate"];
         $ret[$idx]["CREATED"]   = $vacas[$x]["vac_crtdat"];
         $ret[$idx]["UPDUSR"]    = "{$vacas[$x]["user_firstname"]} {$vacas[$x]["user_lastname"]}";
         $ret[$idx]["UPDDAT"]    = $vacas[$x]["vac_upddat"];
         $ret[$idx]["STATDESC"]  = getVacationStatus($ret[$idx]["STAT"]);
         $ret[$idx]["DAYS"]      = (int)$vacas[$x]["vac_days"];
         
         $startdate += 86400;
      }
   }
   return $ret;
}

//----------------------------------------------------------------------------------
function getVacationStatus($status, $small = false)
{
   global $_LANG;
   
   if($small)
   {
      switch((int)$status)
      {
         case 0: return $_LANG["MODULE"]["VAC"][8]; break;
         case 1: return $_LANG["MODULE"]["VAC"][9]; break;
         case 2: return $_LANG["MODULE"]["VAC"][10]; break;
      }
   }
   else
   {
      switch((int)$status)
      {
         case 0: return "<font style=color:red>{$_LANG["MODULE"]["VAC"][8]}</font>"; break;
         case 1: return "<font style=color:darkorange>{$_LANG["MODULE"]["VAC"][9]}</font>"; break;
         case 2: return "<font style=color:green>{$_LANG["MODULE"]["VAC"][10]}</font>"; break;
      }
   }

   return "Error: Unknown";
}

//----------------------------------------------------------------------------------
function getAppointments($CON, $month, $year, $usrid)
{
   $daysecs    = 86400;

   $sql = " select t1.group_id
            from user_group t1, `group` t2
            where
            t1.user_id = {$usrid} and
            t1.group_id = t2.id and
            t2.group_status = 1";
   $usergroups = $CON->select($sql);

   for($x = 0; $x < count($usergroups) && $usergroups != false; $x++)
      $grpstr .= "{$usergroups[$x]["group_id"]},";
   $grpstr = substr($grpstr, 0, -1);
   
   $sql = " select distinct t1.*, t2.user_firstname, t2.user_lastname
            from calendar_appointments t1
            LEFT OUTER JOIN user t2 ON t1.cal_crtusr = t2.id
            LEFT OUTER JOIN calendar_shared_grp t3 ON t1.id = t3.cal_id
            LEFT OUTER JOIN calendar_shared_usr t4 ON t1.id = t4.cal_id
            where
            (
               t1.cal_crtusr = {$usrid} or ";

   if($grpstr != "")
      $sql .= "t3.group_id IN ({$grpstr}) or ";

   $sql .= "   t4.user_id = {$usrid} or
               t1.cal_public = 1
            ) and
            (
               (
                  t1.cal_startmonth = {$month} and
                  t1.cal_startyear  = {$year}
               ) or
               (
                  t1.cal_endmonth   = {$month} and
                  t1.cal_endyear    = {$year}
               ) or
               (
                  t1.cal_endmonth   > {$month} and
                  t1.cal_endyear    = {$year}
               ) or
               (
                  t1.cal_endyear    > {$year}
               )
            )
            order by t1.cal_startdate, t1.cal_enddate asc";
   $appoints = $CON->select($sql);

   for($x = 0; $x < count($appoints) && $appoints != false; $x++)
   {
      $row = $appoints[$x];

      $arridx = date('d.m.Y', $row["cal_startdate"]);

      if(!is_array($ret[$arridx]))
         $ret[$arridx] = Array();

      array_push($ret[$arridx], $row);

      if(date('d.m.Y', $row["cal_startdate"]) !=  date('d.m.Y', $row["cal_enddate"]))
      {
         $diffstart  = mktime(0, 0, 0, date('m', $row["cal_startdate"]), date('d', $row["cal_startdate"]), date('Y', $row["cal_startdate"]));
         $diffend    = mktime(0, 0, 0, date('m', $row["cal_enddate"]), date('d', $row["cal_enddate"]), date('Y', $row["cal_enddate"]));

         while($diffstart < $diffend)
         {
            $diffstart += $daysecs;

            $arridx = date('d.m.Y', $diffstart);

            if(!is_array($ret[$arridx]))
               $ret[$arridx] = Array();

            array_push($ret[$arridx], $row);
         }
      }
   }

   return $ret;
}

//----------------------------------------------------------------------------------
function getDaysofMonth($months, $weekdays, $month, $year)
{
   $daysecs    = 86400;
   
   $loopdate   = mktime(0, 0, 0, (int)$month, 1, (int)$year);
   
   $looprow    = 0;
   
   while($month == date('m', $loopdate))
   {
      $weekday = date('w', $loopdate);
   
      if($weekday == 0)
         $weekday = 7;
   
      $dates[$looprow][$weekday]["DAY"]      = date('d', $loopdate);
      $dates[$looprow][$weekday]["MONTH"]    = date('m', $loopdate);
      $dates[$looprow][$weekday]["YEAR"]     = date('Y', $loopdate);
      $dates[$looprow][$weekday]["WEEKDAY"]  = $weekday;
      $dates[$looprow][$weekday]["RAWDATE"]  = $loopdate;
   
      $dates[$looprow][$weekday]["DAYSTRING"]   = $weekdays[$dates[$looprow][$weekday]["WEEKDAY"]];
      $dates[$looprow][$weekday]["MONTHSTRING"] = $months[(int)$dates[$looprow][$weekday]["MONTH"]];
   
      if($weekday == 7)
         $looprow++;
   
      $loopdate += $daysecs;
   }

   return $dates;
}

//----------------------------------------------------------------------------------
function displaySize($bytes, $precision = 2)
{
   $units = array('B', 'KB', 'MB', 'GB', 'TB');
   
   $bytes = max($bytes, 0);
   $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
   $pow = min($pow, count($units) - 1);
   
   $bytes /= pow(1024, $pow);
   
   return str_replace(".",",",round($bytes, $precision)). ' ' . $units[$pow];
}

//----------------------------------------------------------------------------------
function displayDate($datestr)
{
   if((int)$datestr)
      return date('d.m.y H:i', $datestr);
   else
      return "&nbsp;";
}

//----------------------------------------------------------------------------------
function rgb2hex2rgb($c)
{
   if(!$c) return false;
   $c = trim($c);
   $out = false;
   if(eregi("^[0-9ABCDEFabcdef\#]+$", $c))
   {
      $c = str_replace('#','', $c);
      $l = strlen($c) == 3 ? 1 : (strlen($c) == 6 ? 2 : false);

      if($l)
      {
         unset($out);
         $out[0] = $out['r'] = $out['red'] = hexdec(substr($c, 0,1*$l));
         $out[1] = $out['g'] = $out['green'] = hexdec(substr($c, 1*$l,1*$l));
         $out[2] = $out['b'] = $out['blue'] = hexdec(substr($c, 2*$l,1*$l));
      }
      else
         $out = false;

   }
   elseif (eregi("^[0-9]+(,| |.)+[0-9]+(,| |.)+[0-9]+$", $c))
   {
      $spr = str_replace(array(',',' ','.'), ':', $c);
      $e = explode(":",$spr);
      if(count($e) != 3) return false;
         $out = '#';
         for($i = 0; $i<3; $i++)
            $e[$i] = dechex(($e[$i] <= 0)?0:(($e[$i] >= 255)?255:$e[$i]));

         for($i = 0; $i<3; $i++)
            $out .= ((strlen($e[$i]) < 2)?'0':'').$e[$i];

         $out = strtoupper($out);
   }
   else
      $out = false;

   return $out;
}

//----------------------------------------------------------------------------------
function colourBrightness($hex, $percent)
{
   $hash = '';
   if (stristr($hex,'#')) {
      $hex = str_replace('#','',$hex);
      $hash = '#';
   }

   $rgb = array(hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2)));

   for ($i=0; $i<3; $i++)
   {
      if ($percent > 0)
      {
         $rgb[$i] = round($rgb[$i] * $percent) + round(255 * (1-$percent));
      }
      else
      {
         $positivePercent = $percent - ($percent*2);
         $rgb[$i] = round($rgb[$i] * $positivePercent) + round(0 * (1-$positivePercent));
      }

      if ($rgb[$i] > 255)
      {
         $rgb[$i] = 255;
      }
   }

   $hex = '';
   for($i=0; $i < 3; $i++)
   {
      $hexDigit = dechex($rgb[$i]);

      if(strlen($hexDigit) == 1)
      {
         $hexDigit = "0" . $hexDigit;
      }
      $hex .= $hexDigit;
   }
   return $hash.$hex;
}

//----------------------------------------------------------------------------------
function getRowColor($idx)
{
   $idx++;
   if($idx % 2 == 0)
      return $_SESSION["_PAGE"]->getEffectVal("js_content_second_row");

   return $_SESSION["_PAGE"]->getEffectVal("js_content_first_row");;
}

//----------------------------------------------------------------------------------
function buygetStatsResultOrderCallbackRes($a, $b)
{
   if((int)$a["invc_receipt_date"] == (int)$b["invc_receipt_date"])
      return ((int)$a["invc_docnumber"] > (int)$b["invc_docnumber"]);
   else
      return ((int)$a["invc_receipt_date"] > (int)$b["invc_receipt_date"]);
}

//----------------------------------------------------------------------------------
function printDSCType($type)
{
   if(!(int)$type)
      return "%";
   return $_SESSION["_CONF"]["conf_currency"];
}

//----------------------------------------------------------------------------------
function printPrice($val, $decplaces = -1, $blankifnull = false)
{
   if($decplaces == -1)
      $val = sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $val);
   else
      $val = sprintf("%.{$decplaces}f", $val);

   if((int)$_SESSION["_CONF"]["conf_number_decimal_places"])
   {
      $pre_val = substr($val, 0, strrpos($val, "."));
      $suf_val = substr($val, strrpos($val, ".") +1);

      if($_SESSION["_CONF"]["conf_number_thousand_point"] != "")
         $pre_val = number_format($pre_val, 0, ',', '.');

      $retval = $pre_val.$_SESSION["_CONF"]["conf_number_decimal_point"].$suf_val;
   }
   else
   {
      if($decplaces == -1)
         $dspdec = 0;
      else
         $dspdec = $decplaces;
         
      $retval = $val;
      if($_SESSION["_CONF"]["conf_number_thousand_point"] != "")
         $retval = number_format($retval, $dspdec, ',', '.');
   }

   
   if(strpos($retval, $_SESSION["_CONF"]["conf_number_decimal_point"]) !== false)
   {
      $decsuff = substr($retval, strrpos($retval, $_SESSION["_CONF"]["conf_number_decimal_point"]) +1);
      if(!(int)$decsuff)
         $retval = substr($retval, 0, strrpos($retval, $_SESSION["_CONF"]["conf_number_decimal_point"]));
      else
      {
         if(substr($retval, -1) == "0")
            $retval = substr($retval, 0, -1);
         if(substr($retval, -1) == "0")
            $retval = substr($retval, 0, -1);
         if(substr($retval, -1) == "0")
            $retval = substr($retval, 0, -1);
         if(substr($retval, -1) == "0")
            $retval = substr($retval, 0, -1);
      }
   }

   if($blankifnull && $retval == "0")
      return "&nbsp;";
      

   return $retval;
}

//----------------------------------------------------------------------------------
function getPrice($val, $decplaces = -1)
{
   $val = (float)str_replace(",", ".", str_replace(".", "", $val));
   
   if($decplaces == -1)
      $val = sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $val);
   else
      $val = sprintf("%.{$decplaces}f", $val);

   return (float)$val;
}

//----------------------------------------------------------------------------------
function getSaveMessage($mode)
{
   global $_LANG;
   
   if($mode)
      $savemsg = "<b class='msg_save_ok'>{$_LANG["FORM"]["MESSAGE"][0]}</b>";
   else
      $savemsg = "<b class='msg_save_err'>{$_LANG["FORM"]["MESSAGE"][1]}</b>";

   return $savemsg;
}

//----------------------------------------------------------------------------------
function round_up($value, $places=0)
{
  if ($places < 0) { $places = 0; }
  $mult = pow(10, $places);
  return ceil($value * $mult) / $mult;
}

function round_down($zahl,$decimals=2)
{   
   return floor($zahl*pow(10,$decimals))/pow(10,$decimals);
}


?>