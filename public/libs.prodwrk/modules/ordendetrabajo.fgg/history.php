<?php
$sql = " select t4.*, t5.mant_title, t7.req_number, t1.prd_number, t8.cust_name,
                t2x.item_number_prod, t2x.item_title, t5x.pause_name, t5x.pause_code,
                t5.mant_code, t2.ag_equipotype_id, t2.ag_prdid, t1x.item_amount
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
         where
         t1.prd_status  = 2 and
         t2.ag_status   > 0 and
         t3.wok_status  > 0 and
         t4.evt_status  > 0 and
         t6.win_wrkid      = {$_SESSION["wrk_id"]} and
         t6.win_status     > 0 and
         t6.win_plantaid   = {$_SESSION["user_planta_id"]}
         order by t4.evt_crtdat desc
         LIMIT 0,100";
         echo("{$_SESSION["wrk_id"]}");
         echo("{$_SESSION["user_planta_id"]}");
$events = $CON->select($sql);
for($x = 0; $x < count($events) && $events != false; $x++)
{
   if($events[$x]["evt_type"] == "prod")
   {
      $sql = " select t1.*, t2.merma_title 'causa'
               from
               prod_worker_ot_defectunits t1
               INNER JOIN prod_mermatypes t2 ON t1.evt_merma_typeid = t2.id
               where
               t1.evt_refid   = {$events[$x]["id"]} and
               t1.evt_type    = 'merma' and
               t1.evt_status  > 0
               UNION ALL
               select t1.*, t2.repair_title 'causa'
               from
               prod_worker_ot_defectunits t1
               INNER JOIN prod_repairtypes t2 ON t1.evt_repair_typeid = t2.id
               where
               t1.evt_refid   = {$events[$x]["id"]} and
               t1.evt_type    = 'repair' and
               t1.evt_status  > 0
               order by 1";
      $defectunits = $CON->select($sql);
      foreach($defectunits AS $defectunit)
         $events[$x]["_DEFECTUNITS"][] = $defectunit;
   }
}
?>
<font style="font-size:20px"><b>Historial de registros</b></font>
<div style="clear:both;height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;padding:10px;color:#666666;font-size:14px;border-radius:5px;padding:20px">
      <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
      <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
      Historico
   </td>
</tr>
</table>
<div style="clear:both;height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="6" cellspacing="0">
      <colgroup>
      </colgroup>
      <tr>
         <td class="tdheader">Fecha</td>
         <td class="tdheader">Hora</td>
         <td class="tdheader">OT</td>
         <td class="tdheader">CC</td>
         <td class="tdheader">Cliente</td>
         <td class="tdheader">Producto</td>
         <td class="tdheader">Evento</td>
         <td class="tdheader">Descripci�n</td>
         <td class="tdheader" align="center">Cantidad</td>
         <td class="tdheader" align="center">Saldo</td>
         <td class="tdheader" align="center">Tiempo</td>
      </tr>
      <?php
      for($x = 0; $x < count($events) && $events != false; $x++)
      {
         unset($_OT_STATS);
         $_OT_SALDO = "&nbsp;";

         $evt_amount = 0;
         $evt_desc   = "";
         $evt_type   = "";
         if($events[$x]["evt_type"] == "mantencion")
         {
            $evt_desc = $events[$x]["mant_title"]." ({$events[$x]["mant_code"]})";
            if($events[$x]["evt_comments"] != "")
               $evt_desc .= " ".$events[$x]["evt_comments"];
            $evt_type = "<font style='color:red'>Mantenci�n</font>";
         }
         if($events[$x]["evt_type"] == "apertura")
         {
            $evt_desc = "";
            $sql = " select *
                     from prod_medidas t1
                     where
                     t1.id = {$events[$x]["evt_medida_fromid"]}";
            $medfrom = $CON->select($sql);
            $medfrom = $medfrom[0];

            $sql = " select *
                     from prod_medidas t1
                     where
                     t1.id = {$events[$x]["evt_medida_toid"]}";
            $medto = $CON->select($sql);
            $medto = $medto[0];

            if($medfrom["id"] && $medto["id"])
            {
               $evt_desc .= "De {$medfrom["med_name"]} a {$medto["med_name"]}";
               if($events[$x]["evt_comments"] != "")
                  $evt_desc .= "<br>";
            }

            $evt_desc .= $events[$x]["evt_comments"];
            $evt_type = "<font style='color:darkorange'>Alistamiento</font>";
         }
         if($events[$x]["evt_type"] == "prod" || $events[$x]["evt_type"] == "prodsericolor")
         {
            $evt_desc   = "";
            if($events[$x]["evt_type"] == "prodsericolor")
            {
               $evt_desc = "Color: {$events[$x]["prod_seri_color"]}";
               if($events[$x]["evt_comments"] != "")
                  $evt_desc .= "<br>";
            }
            $evt_desc  .= $events[$x]["evt_comments"];
            $evt_type   = "<font style='color:green'>Producci�n</font>";
            $evt_amount = $events[$x]["evt_amount"];

            $_OT_STATS = getProdStats($CON, $events[$x]["ag_prdid"], 0, 0, 0, 0, $events[$x]["ag_equipotype_id"]);
            $_OT_SALDO = printPrice($events[$x]["item_amount"] - $_OT_STATS["_PROD_AMOUNT"]);
         }
         if($events[$x]["evt_type"] == "pause")
         {
            $evt_desc   = $events[$x]["pause_name"]." ({$events[$x]["pause_code"]})";
            if($events[$x]["evt_comments"] != "")
               $evt_desc .= " ".$events[$x]["evt_comments"];
            $evt_type   = "<font style='color:darkorange'>Pausa</font>";
            $evt_amount = 0;
         }

         $orig_enddat = (int)$events[$x]["evt_enddat"];
         if(!(int)$events[$x]["evt_enddat"])
            $events[$x]["evt_enddat"] = time();

         $time_diff  = $events[$x]["evt_enddat"] - $events[$x]["evt_crtdat"];
         $time_diffx = $time_diff / 60;
         $hours_diff = (int)($time_diffx / 60);
         $min_diff   = (int)($time_diffx - ($hours_diff * 60));
         ?>
         <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="tdleft" style="font-weight:normal"><?=date("d.m.Y", $events[$x]["evt_crtdat"])?></td>
            <td class="tdnrm"><?=date("H:i:s", $events[$x]["evt_crtdat"])?></td>
            <td class="tdnrm"><?=$events[$x]["prd_number"]?>&nbsp;</td>
            <td class="tdnrm"><nobr><?=$events[$x]["req_number"]?></nobr>&nbsp;</td>
            <td class="tdnrm"><?=$events[$x]["cust_name"]?>&nbsp;</td>
            <td class="tdnrm"><?=$events[$x]["item_number_prod"]?>-<?=$events[$x]["item_title"]?></td>
            <td class="tdnrm"><?=$evt_type?>&nbsp;</td>
            <td class="tdnrm"><?=$evt_desc?>&nbsp;</td>
            <td class="tdnrm" align="center"><?=printPrice($evt_amount,0,true)?></td>
            <td class="tdnrm" align="center"><?=$_OT_SALDO?></td>
            <td class="tdnrm" align="center"><?="{$hours_diff}h {$min_diff}m"?></td>
         </tr>
         <?php
         if(count($events[$x]["_DEFECTUNITS"]))
         {
            foreach($events[$x]["_DEFECTUNITS"] AS $defectunit)
            {
               $mtype = "";
               if($defectunit["evt_type"] == "merma")
                  $mtype = "<font style='color:#BD28A6'>Merma</font>";
               if($defectunit["evt_type"] == "repair")
                  $mtype = "<font style='color:#BD28A6'>Reparaci�n</font>";
               ?>
               <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="tdleft" style="font-weight:normal;background-color:#EEEEEE" colspan="6">
                     <i class="fa fa-fw fa-exclamation-triangle" style='color:#BD28A6'></i>
                     Eventos no conformes
                  </td>
                  <td class="tdnrm" style="background-color:#EEEEEE"><?=$mtype?>&nbsp;</td>
                  <td class="tdnrm" style="background-color:#EEEEEE"><?=$defectunit["causa"]?>&nbsp;</td>
                  <td class="tdnrm" style="background-color:#EEEEEE" align="center"><?=printPrice($defectunit["evt_amount"],0,true)?></td>
                  <td class="tdnrm" style="background-color:#EEEEEE">&nbsp;</td>
                  <td class="tdnrm" style="background-color:#EEEEEE">&nbsp;</td>
               </tr>
               <?php
            }
         }
      }
      if(!$x)
      {  ?>
         <tr>
            <td colspan="11" style="background-color:#FFFFFF;padding:20px" align="center">
               <b class="msg_save_err">
                  No existen datos historicos.
               </b>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
   </td>
</tr>
</table>
